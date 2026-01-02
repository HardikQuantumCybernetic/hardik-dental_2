import { serve } from "https://deno.land/std@0.168.0/http/server.ts"
import { createClient } from 'https://esm.sh/@supabase/supabase-js@2'

// =============================================================================
// SECURITY FIX 1: STRICT CORS CONFIGURATION
// =============================================================================
// Problem: Wildcard (*) CORS allows any website to make requests to our API
// Fix: Explicitly whitelist only trusted origins
// =============================================================================

const ALLOWED_ORIGINS = [
  // Production
  'https://mmsmljkeedqfrbgsqipf.supabase.co',
  // Add your production domain here when deployed
  // 'https://yourdomain.com',
  // 'https://www.yourdomain.com',
  
  // Development (only in non-production)
  ...(Deno.env.get('ENVIRONMENT') !== 'production' ? [
    'http://localhost:3000',
    'http://localhost:5173',
    'http://localhost:8080',
    'http://127.0.0.1:3000',
    'http://127.0.0.1:5173',
    'http://127.0.0.1:8080',
  ] : [])
]

function getCorsHeaders(origin: string | null): Record<string, string> {
  // Validate origin against allowlist
  const allowedOrigin = origin && ALLOWED_ORIGINS.includes(origin) ? origin : ALLOWED_ORIGINS[0]
  
  return {
    'Access-Control-Allow-Origin': allowedOrigin,
    'Access-Control-Allow-Methods': 'POST, OPTIONS',
    'Access-Control-Allow-Headers': 'authorization, x-client-info, apikey, content-type',
    'Access-Control-Allow-Credentials': 'true',
    'Access-Control-Max-Age': '86400', // Cache preflight for 24 hours
  }
}

// =============================================================================
// SECURITY FIX 2: RATE LIMITING
// =============================================================================
// Problem: No rate limiting allows brute-force attacks and API abuse
// Fix: Implement IP-based and user-based rate limiting with configurable limits
// =============================================================================

// In-memory rate limit store (for edge function context)
// Note: For production with multiple instances, use Redis or Supabase table
const rateLimitStore = new Map<string, { count: number; resetTime: number }>()

interface RateLimitConfig {
  maxRequests: number
  windowMs: number
}

// Configurable via environment variables with sensible defaults
const RATE_LIMIT_CONFIG: RateLimitConfig = {
  maxRequests: parseInt(Deno.env.get('RATE_LIMIT_MAX_REQUESTS') || '100'),
  windowMs: parseInt(Deno.env.get('RATE_LIMIT_WINDOW_MS') || '60000'), // 1 minute
}

function checkRateLimit(identifier: string): { 
  allowed: boolean
  remaining: number
  resetTime: number 
  retryAfter: number
} {
  const now = Date.now()
  const record = rateLimitStore.get(identifier)
  
  if (!record || now > record.resetTime) {
    // New window
    const resetTime = now + RATE_LIMIT_CONFIG.windowMs
    rateLimitStore.set(identifier, { count: 1, resetTime })
    return { 
      allowed: true, 
      remaining: RATE_LIMIT_CONFIG.maxRequests - 1,
      resetTime,
      retryAfter: 0
    }
  }
  
  if (record.count >= RATE_LIMIT_CONFIG.maxRequests) {
    // Rate limit exceeded
    const retryAfter = Math.ceil((record.resetTime - now) / 1000)
    return { 
      allowed: false, 
      remaining: 0,
      resetTime: record.resetTime,
      retryAfter
    }
  }
  
  // Increment counter
  record.count++
  return { 
    allowed: true, 
    remaining: RATE_LIMIT_CONFIG.maxRequests - record.count,
    resetTime: record.resetTime,
    retryAfter: 0
  }
}

function getRateLimitHeaders(rateLimit: { remaining: number; resetTime: number }): Record<string, string> {
  return {
    'X-RateLimit-Limit': RATE_LIMIT_CONFIG.maxRequests.toString(),
    'X-RateLimit-Remaining': rateLimit.remaining.toString(),
    'X-RateLimit-Reset': Math.ceil(rateLimit.resetTime / 1000).toString(),
  }
}

// =============================================================================
// SECURITY FIX 3: AUTHENTICATION & AUTHORIZATION
// =============================================================================
// Problem: API accepts requests without authentication
// Fix: Validate JWT tokens and enforce user authentication
// =============================================================================

interface AuthResult {
  authenticated: boolean
  userId?: string
  error?: string
}

async function validateAuth(req: Request): Promise<AuthResult> {
  const authHeader = req.headers.get('authorization')
  
  if (!authHeader) {
    return { authenticated: false, error: 'Missing authorization header' }
  }
  
  const token = authHeader.replace('Bearer ', '')
  
  if (!token) {
    return { authenticated: false, error: 'Invalid token format' }
  }
  
  try {
    // Create Supabase client to verify the token
    const supabaseUrl = Deno.env.get('SUPABASE_URL')!
    const supabaseKey = Deno.env.get('SUPABASE_ANON_KEY')!
    
    const supabase = createClient(supabaseUrl, supabaseKey, {
      auth: {
        autoRefreshToken: false,
        persistSession: false
      },
      global: {
        headers: {
          Authorization: `Bearer ${token}`
        }
      }
    })
    
    // Verify the token by getting the user
    const { data: { user }, error } = await supabase.auth.getUser(token)
    
    if (error || !user) {
      console.error('Auth validation failed:', error?.message)
      return { authenticated: false, error: 'Invalid or expired token' }
    }
    
    return { authenticated: true, userId: user.id }
  } catch (error) {
    console.error('Auth error:', error)
    return { authenticated: false, error: 'Authentication failed' }
  }
}

// =============================================================================
// MAIN REQUEST HANDLER
// =============================================================================

serve(async (req) => {
  const origin = req.headers.get('origin')
  const corsHeaders = getCorsHeaders(origin)
  
  // Handle CORS preflight requests
  if (req.method === 'OPTIONS') {
    return new Response(null, { 
      status: 204,
      headers: corsHeaders 
    })
  }
  
  // Only allow POST method
  if (req.method !== 'POST') {
    return new Response(
      JSON.stringify({ error: 'Method not allowed' }),
      { 
        status: 405, 
        headers: { ...corsHeaders, 'Content-Type': 'application/json' } 
      }
    )
  }
  
  // Validate origin
  if (origin && !ALLOWED_ORIGINS.includes(origin)) {
    console.warn(`Blocked request from unauthorized origin: ${origin}`)
    return new Response(
      JSON.stringify({ error: 'Origin not allowed' }),
      { 
        status: 403, 
        headers: { ...corsHeaders, 'Content-Type': 'application/json' } 
      }
    )
  }

  try {
    // =======================================================================
    // SECURITY CHECK 1: Authentication
    // =======================================================================
    const authResult = await validateAuth(req)
    
    if (!authResult.authenticated) {
      console.warn('Unauthenticated request blocked:', authResult.error)
      return new Response(
        JSON.stringify({ 
          error: 'Unauthorized',
          message: authResult.error 
        }),
        { 
          status: 401, 
          headers: { ...corsHeaders, 'Content-Type': 'application/json' } 
        }
      )
    }
    
    // =======================================================================
    // SECURITY CHECK 2: Rate Limiting (by user ID for authenticated requests)
    // =======================================================================
    const rateLimitKey = `user:${authResult.userId}`
    const rateLimit = checkRateLimit(rateLimitKey)
    const rateLimitHeaders = getRateLimitHeaders(rateLimit)
    
    if (!rateLimit.allowed) {
      console.warn(`Rate limit exceeded for user: ${authResult.userId}`)
      return new Response(
        JSON.stringify({ 
          error: 'Too Many Requests',
          message: `Rate limit exceeded. Try again in ${rateLimit.retryAfter} seconds.`,
          retryAfter: rateLimit.retryAfter
        }),
        { 
          status: 429, 
          headers: { 
            ...corsHeaders, 
            ...rateLimitHeaders,
            'Retry-After': rateLimit.retryAfter.toString(),
            'Content-Type': 'application/json' 
          } 
        }
      )
    }
    
    // =======================================================================
    // BUSINESS LOGIC (with security measures in place)
    // =======================================================================
    const { message, context } = await req.json()
    
    // Input validation
    if (!message || typeof message !== 'string') {
      return new Response(
        JSON.stringify({ error: 'Invalid request', message: 'Message is required' }),
        { 
          status: 400, 
          headers: { ...corsHeaders, ...rateLimitHeaders, 'Content-Type': 'application/json' } 
        }
      )
    }
    
    // Limit message length to prevent abuse
    const MAX_MESSAGE_LENGTH = 2000
    if (message.length > MAX_MESSAGE_LENGTH) {
      return new Response(
        JSON.stringify({ 
          error: 'Invalid request', 
          message: `Message exceeds maximum length of ${MAX_MESSAGE_LENGTH} characters` 
        }),
        { 
          status: 400, 
          headers: { ...corsHeaders, ...rateLimitHeaders, 'Content-Type': 'application/json' } 
        }
      )
    }
    
    // Use environment variable for API key (secure - never hardcoded)
    const GEMINI_API_KEY = Deno.env.get('GEMINI_API_KEY')
    
    if (!GEMINI_API_KEY) {
      console.error('Gemini API key not found in environment variables')
      throw new Error('Gemini API key not configured')
    }

    const response = await fetch(
      'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.0-flash:generateContent',
      {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-goog-api-key': GEMINI_API_KEY,
        },
        body: JSON.stringify({
          contents: [
            {
              parts: [
                {
                  text: `${context || ''}\n\nPatient Question: ${message}\n\nPlease provide a helpful, professional response about dental care, services, or general oral health information. Keep responses concise and informative.`
                }
              ]
            }
          ],
          generationConfig: {
            temperature: 0.7,
            topK: 40,
            topP: 0.95,
            maxOutputTokens: 1024,
          },
        }),
      }
    )

    const data = await response.json()
    
    if (!response.ok) {
      console.error('Gemini API error:', data)
      throw new Error(data.error?.message || 'Failed to get response from Gemini')
    }

    const generatedText = data.candidates?.[0]?.content?.parts?.[0]?.text || 
      "I'm here to help with your dental questions. Please call our office at (808) 095-0921 for specific medical advice."

    console.log(`Request processed successfully for user: ${authResult.userId}`)
    
    return new Response(
      JSON.stringify({ response: generatedText }),
      {
        headers: { 
          ...corsHeaders, 
          ...rateLimitHeaders,
          'Content-Type': 'application/json' 
        },
      },
    )
  } catch (error) {
    console.error('Error:', error)
    
    return new Response(
      JSON.stringify({ 
        error: error instanceof Error ? error.message : 'Unknown error occurred',
        response: "I'm experiencing technical difficulties. Please call our office at (808) 095-0921 for assistance."
      }),
      {
        status: 500,
        headers: { ...corsHeaders, 'Content-Type': 'application/json' },
      },
    )
  }
})
