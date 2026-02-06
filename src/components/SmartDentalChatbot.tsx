import React, { useState, useRef, useEffect } from 'react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { ScrollArea } from '@/components/ui/scroll-area';
import { Card } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { MessageCircle, X, Send, User, Bot, Phone, Calendar, Clock, MapPin, Zap, Sparkles, Heart, Shield, HelpCircle, Minus } from 'lucide-react';
import { cn } from '@/lib/utils';
import { toast } from 'sonner';
import { chatbotService } from '@/lib/supabase';

interface Message {
  id: string;
  content: string;
  isBot: boolean;
  timestamp: Date;
  type?: 'text' | 'quick-reply' | 'info';
  suggestedQuestions?: string[];
}

interface QuickAction {
  icon?: React.ElementType;
  text: string;
  action: () => void;
  variant?: 'default' | 'emergency' | 'booking';
}

interface QADataset {
  question: string;
  answer: string;
  category: string;
  keywords: string[];
  relatedQuestions: string[];
}

export const SmartDentalChatbot: React.FC = () => {
  const [isOpen, setIsOpen] = useState(false);
  const [isMinimized, setIsMinimized] = useState(false);
  const [messages, setMessages] = useState<Message[]>([
    {
      id: '1',
      content: "🦷 Welcome to Hardik Dental Practice! I'm your smart dental assistant powered by AI.\n\nI can help you with:\n• 📅 Appointment scheduling\n• 🏥 Service information  \n• ⏰ Office hours & location\n• 💳 Insurance & payments\n• 🚨 Emergency guidance\n• 🪥 Dental care tips\n\nHow can I assist you today?",
      isBot: true,
      timestamp: new Date(),
      type: 'info',
      suggestedQuestions: ["What services do you offer?", "How do I book an appointment?", "What are your hours?", "Do you accept insurance?", "Do you have emergency services?"]
    }
  ]);
  const [inputMessage, setInputMessage] = useState('');
  const [isLoading, setIsLoading] = useState(false);
  const [isTyping, setIsTyping] = useState(false);
  const messagesEndRef = useRef<HTMLDivElement>(null);

  const scrollToBottom = () => {
    messagesEndRef.current?.scrollIntoView({ behavior: 'smooth' });
  };

  useEffect(() => {
    scrollToBottom();
  }, [messages]);

  // Comprehensive QA Dataset (keeping the same structure)
  const qaDataset: QADataset[] = [
    // Opening Hours Questions
    { question: "What are your opening hours?", answer: "Our clinic is open Monday to Saturday from 9:00 AM to 6:00 PM.", category: "hours", keywords: ["opening", "hours", "open"], relatedQuestions: ["Are you open on weekends?", "What time do you close?", "Can I book an appointment?", "Do you have emergency services?", "Where are you located?"] },
    { question: "Is the dentist open on weekends?", answer: "Yes, we are open on Saturdays from 9:00 AM to 2:00 PM. We are closed on Sundays.", category: "hours", keywords: ["weekends", "saturday", "sunday"], relatedQuestions: ["What are your opening hours?", "Can I book a weekend appointment?", "Do you have emergency services on weekends?", "What time do you close on Saturday?", "How do I book an appointment?"] },
    { question: "What time does the dentist shop close?", answer: "We close at 6:00 PM on weekdays and 2:00 PM on Saturdays.", category: "hours", keywords: ["close", "closing", "time"], relatedQuestions: ["What are your opening hours?", "Are you open on weekends?", "How do I book an appointment?", "Do you have late appointments?", "Can I call after hours?"] },
    
    // Appointments & Booking
    { question: "How can I book an appointment?", answer: "You can book an appointment by calling us at (808) 095-0921 or using our online booking system on our website.", category: "booking", keywords: ["book", "appointment", "schedule"], relatedQuestions: ["Can I book online?", "Do you accept walk-ins?", "What are your hours?", "How far in advance should I book?", "What should I bring?"] },
    { question: "Can I book an appointment online?", answer: "Yes, you can book your appointment online through our website 24/7.", category: "booking", keywords: ["online", "book", "website"], relatedQuestions: ["How do I book an appointment?", "Do you accept walk-ins?", "What services can I book online?", "How do I create an account?", "Can I reschedule online?"] },
    
    // Services
    { question: "What types of dental services do you provide?", answer: "We offer general dentistry, teeth cleaning, fillings, root canals, orthodontics (braces), cosmetic dentistry, teeth whitening, and emergency care.", category: "services", keywords: ["services", "treatment", "provide"], relatedQuestions: ["Do you do teeth cleaning?", "Do you offer braces?", "Can I get teeth whitening?", "How much do treatments cost?", "Do you accept insurance?"] },
    { question: "Do you offer teeth cleaning?", answer: "Yes, professional teeth cleaning is one of our popular services.", category: "services", keywords: ["cleaning", "teeth", "hygiene"], relatedQuestions: ["What services do you provide?", "How much does cleaning cost?", "How often should I get cleaning?", "Do you accept insurance?", "How do I book a cleaning?"] },
    
    // Insurance & Payment
    { question: "What insurance do you accept?", answer: "We accept most major dental insurance plans. Please contact us for specific details at (808) 095-0921.", category: "insurance", keywords: ["insurance", "accept", "plans"], relatedQuestions: ["Do you offer payment plans?", "How much do treatments cost?", "Can I verify my benefits?", "What if I don't have insurance?", "Do you take cash?"] },
    
    // Contact Information
    { question: "How do I contact your office?", answer: "You can call us at (808) 095-0921, email us, or use the contact form on our website.", category: "contact", keywords: ["contact", "phone", "email"], relatedQuestions: ["What are your hours?", "Where are you located?", "Do you have emergency contact?", "How do I book an appointment?", "Can I text you?"] },
    { question: "Where is your dentist shop located?", answer: "We are located at our main office. You can find us on Google Maps or call (808) 095-0921 for directions.", category: "location", keywords: ["location", "address", "where"], relatedQuestions: ["How do I contact you?", "Is parking available?", "Are you accessible by public transport?", "What are your hours?", "How do I get directions?"] },
    
    // Emergency Care
    { question: "Do you offer emergency dental care?", answer: "Yes, we offer emergency appointments for issues like pain, injury, or broken teeth. Call (808) 095-0921 immediately.", category: "emergency", keywords: ["emergency", "urgent", "pain"], relatedQuestions: ["What should I do for dental emergency?", "What are your emergency hours?", "How much do emergency visits cost?", "Can I get same-day appointments?", "What qualifies as emergency?"] },
  ];

  const findBestMatch = (userQuery: string): QADataset | null => {
    const query = userQuery.toLowerCase().trim();
    
    // Exact match first
    const exactMatch = qaDataset.find(qa => 
      qa.question.toLowerCase() === query
    );
    if (exactMatch) return exactMatch;

    // Keyword matching
    let bestMatch: QADataset | null = null;
    let highestScore = 0;

    for (const qa of qaDataset) {
      let score = 0;
      
      // Check keywords
      for (const keyword of qa.keywords) {
        if (query.includes(keyword.toLowerCase())) {
          score += 2;
        }
      }
      
      // Check question words
      const questionWords = qa.question.toLowerCase().split(' ');
      for (const word of questionWords) {
        if (query.includes(word) && word.length > 3) {
          score += 1;
        }
      }

      if (score > highestScore) {
        highestScore = score;
        bestMatch = qa;
      }
    }

    return highestScore >= 2 ? bestMatch : null;
  };

  const getAIResponse = async (userMessage: string): Promise<{ answer: string; relatedQuestions: string[] }> => {
    // First try local QA dataset
    const localMatch = findBestMatch(userMessage);
    if (localMatch) {
      return {
        answer: localMatch.answer,
        relatedQuestions: localMatch.relatedQuestions.slice(0, 3)
      };
    }

    // If no local match, try the AI service
    try {
      const aiResponse = await chatbotService.sendMessage(userMessage);
      return {
        answer: aiResponse,
        relatedQuestions: ["What services do you offer?", "How do I book an appointment?", "What are your hours?"]
      };
    } catch (error) {
      console.error('AI service error:', error);
      return {
        answer: "I apologize, but I'm having trouble understanding your question. Could you please rephrase it? You can also call us at (808) 095-0921 for immediate assistance.",
        relatedQuestions: ["What services do you offer?", "How do I book an appointment?", "What are your hours?"]
      };
    }
  };

  const handleSendMessage = async () => {
    if (!inputMessage.trim() || isLoading) return;

    const userMessage: Message = {
      id: Date.now().toString(),
      content: inputMessage,
      isBot: false,
      timestamp: new Date()
    };

    setMessages(prev => [...prev, userMessage]);
    setInputMessage('');
    setIsLoading(true);
    setIsTyping(true);

    try {
      const response = await getAIResponse(inputMessage);
      
      // Simulate typing delay
      await new Promise(resolve => setTimeout(resolve, 800));
      
      const botMessage: Message = {
        id: (Date.now() + 1).toString(),
        content: response.answer,
        isBot: true,
        timestamp: new Date(),
        suggestedQuestions: response.relatedQuestions
      };

      setMessages(prev => [...prev, botMessage]);
    } catch (error) {
      console.error('Error getting response:', error);
      toast.error('Failed to get response. Please try again.');
    } finally {
      setIsLoading(false);
      setIsTyping(false);
    }
  };

  const handleQuickQuestion = (question: string) => {
    setInputMessage(question);
  };

  const quickActions: QuickAction[] = [
    { icon: Phone, text: "Call Now", action: () => window.open('tel:+918080950921'), variant: 'default' },
    { icon: Calendar, text: "Book Appointment", action: () => window.location.href = '/booking', variant: 'booking' },
    { icon: MapPin, text: "Get Directions", action: () => window.open('https://maps.google.com', '_blank'), variant: 'default' },
  ];

  if (!isOpen) {
    return (
      <Button
        onClick={() => setIsOpen(true)}
        className="fixed bottom-6 right-6 w-16 h-16 rounded-full bg-gradient-to-r from-dental-blue to-dental-mint hover:from-dental-blue/90 hover:to-dental-mint/90 shadow-lg z-50 transition-all duration-300 hover:scale-110"
        aria-label="Open chat"
      >
        <MessageCircle className="w-7 h-7 text-white" />
      </Button>
    );
  }

  return (
    <Card className={cn(
      "fixed z-50 shadow-2xl border-dental-blue/20 transition-all duration-300",
      isMinimized 
        ? "bottom-6 right-6 w-80 h-14" 
        : "bottom-6 right-6 w-[380px] h-[600px] max-h-[80vh]"
    )}>
      {/* Header */}
      <div className="flex items-center justify-between p-4 bg-gradient-to-r from-dental-blue to-dental-mint text-white rounded-t-lg">
        <div className="flex items-center gap-3">
          <div className="w-10 h-10 rounded-full bg-white/20 flex items-center justify-center">
            <Bot className="w-6 h-6" />
          </div>
          <div>
            <h3 className="font-semibold">Dental Assistant</h3>
            <div className="flex items-center gap-1 text-xs text-white/80">
              <span className="w-2 h-2 bg-green-400 rounded-full animate-pulse"></span>
              Online
            </div>
          </div>
        </div>
        <div className="flex items-center gap-1">
          <Button
            variant="ghost"
            size="icon"
            className="h-8 w-8 text-white hover:bg-white/20"
            onClick={() => setIsMinimized(!isMinimized)}
          >
            <Minus className="w-4 h-4" />
          </Button>
          <Button
            variant="ghost"
            size="icon"
            className="h-8 w-8 text-white hover:bg-white/20"
            onClick={() => setIsOpen(false)}
          >
            <X className="w-4 h-4" />
          </Button>
        </div>
      </div>

      {!isMinimized && (
        <>
          {/* Quick Actions */}
          <div className="flex gap-2 p-3 border-b bg-dental-blue-light/30">
            {quickActions.map((action, index) => (
              <Button
                key={index}
                variant="outline"
                size="sm"
                className={cn(
                  "flex-1 text-xs",
                  action.variant === 'booking' && "bg-dental-blue text-white hover:bg-dental-blue/90",
                  action.variant === 'emergency' && "bg-red-500 text-white hover:bg-red-600"
                )}
                onClick={action.action}
              >
                {action.icon && <action.icon className="w-3 h-3 mr-1" />}
                {action.text}
              </Button>
            ))}
          </div>

          {/* Messages */}
          <ScrollArea className="flex-1 h-[380px] p-4">
            <div className="space-y-4">
              {messages.map((message) => (
                <div
                  key={message.id}
                  className={cn(
                    "flex gap-3",
                    message.isBot ? "justify-start" : "justify-end"
                  )}
                >
                  {message.isBot && (
                    <div className="w-8 h-8 rounded-full bg-dental-blue/10 flex items-center justify-center flex-shrink-0">
                      <Bot className="w-4 h-4 text-dental-blue" />
                    </div>
                  )}
                  <div
                    className={cn(
                      "max-w-[80%] rounded-2xl px-4 py-3",
                      message.isBot
                        ? "bg-dental-blue-light text-foreground rounded-tl-none"
                        : "bg-dental-blue text-white rounded-tr-none"
                    )}
                  >
                    <p className="text-sm whitespace-pre-line">{message.content}</p>
                    
                    {message.suggestedQuestions && message.suggestedQuestions.length > 0 && (
                      <div className="mt-3 space-y-2">
                        <p className="text-xs font-medium opacity-70">Quick questions:</p>
                        <div className="flex flex-wrap gap-1">
                          {message.suggestedQuestions.map((q, i) => (
                            <Badge
                              key={i}
                              variant="outline"
                              className="cursor-pointer text-xs hover:bg-dental-blue hover:text-white transition-colors"
                              onClick={() => handleQuickQuestion(q)}
                            >
                              {q}
                            </Badge>
                          ))}
                        </div>
                      </div>
                    )}
                  </div>
                  {!message.isBot && (
                    <div className="w-8 h-8 rounded-full bg-dental-mint/20 flex items-center justify-center flex-shrink-0">
                      <User className="w-4 h-4 text-dental-mint" />
                    </div>
                  )}
                </div>
              ))}
              
              {isTyping && (
                <div className="flex gap-3 justify-start">
                  <div className="w-8 h-8 rounded-full bg-dental-blue/10 flex items-center justify-center">
                    <Bot className="w-4 h-4 text-dental-blue" />
                  </div>
                  <div className="bg-dental-blue-light rounded-2xl rounded-tl-none px-4 py-3">
                    <div className="flex gap-1">
                      <span className="w-2 h-2 bg-dental-gray rounded-full animate-bounce"></span>
                      <span className="w-2 h-2 bg-dental-gray rounded-full animate-bounce" style={{ animationDelay: '0.2s' }}></span>
                      <span className="w-2 h-2 bg-dental-gray rounded-full animate-bounce" style={{ animationDelay: '0.4s' }}></span>
                    </div>
                  </div>
                </div>
              )}
              
              <div ref={messagesEndRef} />
            </div>
          </ScrollArea>

          {/* Input */}
          <div className="p-4 border-t bg-background">
            <form
              onSubmit={(e) => {
                e.preventDefault();
                handleSendMessage();
              }}
              className="flex gap-2"
            >
              <Input
                value={inputMessage}
                onChange={(e) => setInputMessage(e.target.value)}
                placeholder="Type your message..."
                className="flex-1 border-dental-blue-light focus-visible:ring-dental-blue"
                disabled={isLoading}
              />
              <Button
                type="submit"
                size="icon"
                className="bg-dental-blue hover:bg-dental-blue/90"
                disabled={isLoading || !inputMessage.trim()}
              >
                <Send className="w-4 h-4" />
              </Button>
            </form>
            <p className="text-xs text-center text-dental-gray mt-2">
              Powered by AI • Available 24/7
            </p>
          </div>
        </>
      )}
    </Card>
  );
};

export default SmartDentalChatbot;
