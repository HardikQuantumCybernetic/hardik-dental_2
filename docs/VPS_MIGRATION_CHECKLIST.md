 # VPS Migration Checklist - Dental Management System
 
 ## Complete Guide for Converting from Supabase to PHP + Oracle
 
 This document lists ALL changes needed to make your website work on a VPS with PHP + Oracle backend instead of Supabase.
 
 ---
 
 ## 📋 PRIORITY 1: Critical Changes (Must Do)
 
 ### 1.1 Create TypeScript API Client
 **File to create:** `src/lib/api.ts`
 
 This file will replace all Supabase SDK calls with fetch requests to your PHP backend.
 
 **What it needs to contain:**
 - Base API URL configuration
 - HTTP client wrapper with proper error handling
 - All service methods mirroring current Supabase services:
   - `patientService` (getAll, create, update, delete)
   - `appointmentService` (getAll, create, update, delete)
   - `feedbackService` (getAll, create, update)
   - `doctorService` (getAll)
   - `serviceService` (getAll)
   - `patientServiceService` (getByPatientId, create, update, delete)
   - `patientFinancialService` (getByPatientId, create, update, upsert)
 - Auth methods (signIn, signOut, getSession)
 
 ---
 
 ### 1.2 Files That Import Supabase (MUST UPDATE)
 
 These files directly import `supabase` from `@/integrations/supabase/client` or `@/lib/supabase`:
 
 | File | Changes Needed |
 |------|----------------|
 | `src/lib/supabase.ts` | Replace Supabase SDK calls with API fetch calls |
 | `src/hooks/useSupabase.ts` | Remove real-time subscriptions, use API client |
 | `src/hooks/useSupabaseExtended.ts` | Remove real-time subscriptions, use API client |
 | `src/hooks/useAdminAuth.ts` | Replace Supabase Auth with PHP auth API |
 | `src/components/DentalBooking.tsx` | Use API client for appointments & patients |
 | `src/components/FeedbackPage.tsx` | Use API client for feedback submission |
 | `src/components/SmartDentalChatbot.tsx` | Update edge function call to PHP endpoint |
 | `src/components/auth/PatientDashboard.tsx` | Use API client for patient data |
 | `src/components/admin/DatabaseStatus.tsx` | Update health check to PHP endpoint |
 | `src/components/contact/ContactForm.tsx` | Uses `useFeedback` hook (indirect) |
 | `src/components/admin/AdminDashboard.tsx` | Uses hooks (indirect) |
 | `src/components/admin/PatientManagementSupabase.tsx` | Uses hooks (indirect) |
 | `src/components/admin/AppointmentManagement.tsx` | Uses hooks (indirect) |
 | `src/components/admin/AppointmentScheduling.tsx` | Uses hooks (indirect) |
 | `src/components/admin/PatientFinancialManager.tsx` | Uses hooks (indirect) |
 | `src/components/admin/FeedbackManagement.tsx` | Uses hooks (indirect) |
 | `src/components/admin/ReportsAnalytics.tsx` | Uses hooks (indirect) |
 | `src/components/optimized/CriticalResourceLoader.tsx` | Remove Supabase preconnect |
 | `src/utils/pdfGenerator.ts` | Only imports types - no changes needed |
 
 ---
 
 ### 1.3 Remove Real-time Subscriptions
 
 **Files with Supabase real-time subscriptions that need removal:**
 
 | File | Subscription Code to Remove |
 |------|----------------------------|
 | `src/hooks/useSupabase.ts` | `supabase.channel('patients_changes_in_hook')` |
 | `src/hooks/useSupabase.ts` | `supabase.channel('appointments_changes')` |
 | `src/hooks/useSupabaseExtended.ts` | `supabase.channel('feedback_changes')` |
 
 **Replacement Options:**
 1. Polling (simple): Refetch data every 30 seconds
 2. WebSocket server (complex): Implement PHP WebSocket for real-time
 3. Remove real-time (recommended): Just use manual refresh
 
 ---
 
 ## 📋 PRIORITY 2: Authentication Changes
 
 ### 2.1 Admin Authentication (`src/hooks/useAdminAuth.ts`)
 
 **Current Implementation:**
 ```typescript
 // Uses Supabase Auth
 supabase.auth.signInWithPassword({ email, password })
 supabase.auth.signOut()
 supabase.auth.getSession()
 supabase.auth.onAuthStateChange()
 ```
 
 **Required Changes:**
 ```typescript
 // Replace with PHP API calls
 fetch('/api/auth?action=signin', { method: 'POST', body: JSON.stringify({ email, password }) })
 fetch('/api/auth?action=signout', { method: 'POST' })
 fetch('/api/auth?action=session', { method: 'GET' })
 // Use localStorage or cookies for session management
 ```
 
 ### 2.2 Admin Role Check
 
 **Current:** Queries `user_roles` table via Supabase
 **Required:** Add role checking to PHP `auth.php` endpoint
 
 ### 2.3 Patient Authentication (Clerk)
 
 **Status:** ✅ NO CHANGES NEEDED - Clerk authentication is separate and will continue working
 
 ---
 
 ## 📋 PRIORITY 3: Chatbot/Edge Function Changes
 
 ### 3.1 SmartDentalChatbot.tsx (Line ~244)
 
 **Current:**
 ```typescript
 const { data, error } = await supabase.functions.invoke('chat-with-gemini', {
   body: { message, context }
 });
 ```
 
 **Required Change:**
 ```typescript
 const response = await fetch('YOUR_VPS_URL/api/chatbot.php', {
   method: 'POST',
   headers: { 'Content-Type': 'application/json' },
   body: JSON.stringify({ message, context })
 });
 const data = await response.json();
 ```
 
 **Backend Task:** Create `backend-php/api/chatbot.php` that calls Gemini API
 
 ---
 
 ## 📋 PRIORITY 4: Environment Variables
 
 ### 4.1 Current .env Variables
 
 | Variable | Keep/Remove | Notes |
 |----------|-------------|-------|
 | `VITE_SUPABASE_URL` | REMOVE | No longer needed |
 | `VITE_SUPABASE_ANON_KEY` | REMOVE | No longer needed |
 | `VITE_SUPABASE_PUBLISHABLE_KEY` | REMOVE | No longer needed |
 | `VITE_SUPABASE_PROJECT_ID` | REMOVE | No longer needed |
 | `VITE_CLERK_PUBLISHABLE_KEY` | KEEP | Still using Clerk |
 | `CLERK_SECRET_KEY` | KEEP | Still using Clerk |
 | `GEMINI_API_KEY` | MOVE | Move to PHP server .env |
 | `DATABASE_URL` | REMOVE | Replace with Oracle config |
 
 ### 4.2 New Environment Variables Needed
 
 ```env
 # Frontend (.env)
 VITE_API_URL=https://api.yourdomain.com
 VITE_CLERK_PUBLISHABLE_KEY=your_clerk_key
 VITE_APP_NAME=SmileCare Dental
 
 # Backend (backend-php/config/database.php)
 DB_HOST=your_oracle_host
 DB_PORT=1521
 DB_SERVICE=ORCL
 DB_USERNAME=dental_app
 DB_PASSWORD=your_secure_password
 
 # Backend API Keys
 GEMINI_API_KEY=your_gemini_key
 JWT_SECRET=your_jwt_secret
 ```
 
 ---
 
 ## 📋 PRIORITY 5: Build & Deployment Changes
 
 ### 5.1 Update vite.config.ts
 
 Add proxy for local development:
 ```typescript
 server: {
   host: "::",
   port: 8080,
   proxy: {
     '/api': {
       target: 'http://localhost:8000', // PHP server
       changeOrigin: true,
     }
   }
 }
 ```
 
 ### 5.2 Build for Production
 
 ```bash
 # Create production .env
 echo "VITE_API_URL=https://api.yourdomain.com" > .env.production
 
 # Build
 npm run build
 
 # Upload dist/ to VPS
 scp -r dist/* user@vps:/var/www/dental-frontend/
 ```
 
 ---
 
 ## 📋 PRIORITY 6: Database Migration
 
 ### 6.1 Export Data from Supabase
 
 **Option 1: Using Supabase Dashboard**
 1. Go to Table Editor
 2. Export each table as CSV
 3. Import into Oracle using SQL*Loader
 
 **Option 2: Using SQL**
 ```sql
 -- Export from Supabase (run in SQL Editor)
 SELECT * FROM patients;
 SELECT * FROM appointments;
 SELECT * FROM feedback;
 SELECT * FROM services;
 SELECT * FROM doctors;
 SELECT * FROM patient_services;
 SELECT * FROM patient_financials;
 SELECT * FROM user_roles;
 ```
 
 ### 6.2 Oracle Schema Setup
 
 Run the schema script on your Oracle database:
 ```bash
 sqlplus dental_app/password@//host:1521/ORCL @01_oracle_schema.sql
 ```
 
 ---
 
 ## 📋 PRIORITY 7: Files to Create
 
 | File | Purpose | Status |
 |------|---------|--------|
 | `src/lib/api.ts` | TypeScript API client | ❌ TO CREATE |
 | `backend-php/api/chatbot.php` | Gemini AI endpoint | ❌ TO CREATE |
 | `backend-php/api/health.php` | Health check endpoint | ❌ TO CREATE |
 
 ---
 
 ## 📋 PRIORITY 8: Files to Remove (After Migration)
 
 | File/Folder | Reason |
 |-------------|--------|
 | `src/integrations/supabase/` | No longer using Supabase |
 | `supabase/` folder | Edge functions not needed |
 | `supabase/.env` | Contains old credentials |
 
 **Note:** Keep `src/integrations/supabase/types.ts` temporarily for TypeScript types, or migrate types to a new file.
 
 ---
 
 ## 📋 PRIORITY 9: Testing Checklist
 
 ### Frontend Tests
 - [ ] Homepage loads correctly
 - [ ] Navigation works
 - [ ] Booking form submits successfully
 - [ ] Feedback form submits successfully
 - [ ] Contact form submits successfully
 - [ ] Chatbot works (needs PHP endpoint)
 - [ ] Patient login (Clerk) works
 - [ ] Patient dashboard loads appointments
 - [ ] Admin login works
 - [ ] Admin dashboard shows stats
 - [ ] Patient management CRUD works
 - [ ] Appointment management works
 - [ ] Feedback management works
 
 ### Backend Tests
 - [ ] PHP endpoints return JSON
 - [ ] CORS headers are correct
 - [ ] Oracle connection works
 - [ ] Authentication works
 - [ ] Rate limiting works
 
 ---
 
 ## 📋 PRIORITY 10: Minor Fixes
 
 ### 10.1 Runtime Error Fix
 
 **Current Error:** `useSyncExternalStore` not found in SWR
 
 **Fix:** This is likely a version mismatch. Not related to VPS migration but should be fixed:
 ```bash
 npm update swr
 # OR remove if not actively used
 npm uninstall swr
 ```
 
 ### 10.2 CriticalResourceLoader.tsx
 
 Remove Supabase preconnect (line ~29):
 ```diff
 - 'https://mmsmljkeedqfrbgsqipf.supabase.co'
 + 'YOUR_VPS_API_URL'
 ```
 
 ---
 
 ## 📊 Summary: Work Breakdown
 
 | Category | Estimated Effort | Files Affected |
 |----------|------------------|----------------|
 | Create API Client | 2-3 hours | 1 new file |
 | Update Hooks | 2-3 hours | 3 files |
 | Update Components | 3-4 hours | 10+ files |
 | Auth Migration | 2-3 hours | 2-3 files |
 | Chatbot Endpoint | 1-2 hours | 2 files |
 | Testing | 2-3 hours | - |
 | **Total** | **12-18 hours** | **~20 files** |
 
 ---
 
 ## 🚀 Step-by-Step Implementation Order
 
 1. **Create `src/lib/api.ts`** - Central API client
 2. **Update `src/lib/supabase.ts`** - Replace service implementations
 3. **Update hooks** - Remove real-time, use API client
 4. **Update auth** - Replace Supabase Auth
 5. **Update components** - Fix direct Supabase imports
 6. **Create chatbot endpoint** - PHP + Gemini
 7. **Update environment** - New .env variables
 8. **Test locally** - Run PHP dev server + Vite
 9. **Deploy to VPS** - Upload and configure
 10. **Migrate data** - Export Supabase → Import Oracle
 
 ---
 
 ## ⚠️ Important Notes
 
 1. **Clerk Authentication**: Patient-facing auth (Clerk) remains unchanged. Only admin auth (Supabase Auth) needs migration.
 
 2. **Real-time Features**: Will be lost unless you implement WebSocket server in PHP.
 
 3. **Edge Functions**: The Gemini chatbot uses Supabase Edge Functions. You need to create a PHP equivalent.
 
 4. **Type Safety**: Consider keeping type definitions or migrating them to a shared types file.
 
 5. **CORS**: Make sure your PHP backend allows requests from your frontend domain.