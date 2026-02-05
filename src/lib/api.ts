 /**
  * ============================================================================
  * API Client for PHP Backend
  * ============================================================================
  * This file replaces Supabase SDK calls with fetch requests to the PHP backend.
  * 
  * CONFIGURATION:
  * Set VITE_API_URL in your .env file to point to your PHP backend.
  * Example: VITE_API_URL=https://api.yourdomain.com
  * ============================================================================
  */
 
 // Base API URL - defaults to localhost for development
 const API_BASE_URL = import.meta.env.VITE_API_URL || 'http://localhost:8000/api';
 
 // ============================================================================
 // Types (Matching existing Supabase types)
 // ============================================================================
 
 export type Patient = {
   id: string;
   created_at: string;
   name: string;
   email: string;
   phone: string;
   date_of_birth: string;
   address: string;
   medical_history: string;
   insurance_info: string;
   status: 'active' | 'inactive';
   patient_id: string | null;
 };
 
 export type Appointment = {
   id: string;
   created_at: string;
   patient_id: string;
   appointment_date: string;
   appointment_time: string;
   service_type: string;
   doctor: string;
   status: 'scheduled' | 'confirmed' | 'completed' | 'cancelled' | 'no-show';
   notes: string;
 };
 
 export type Feedback = {
   id: string;
   created_at: string;
   updated_at: string;
   patient_name: string;
   patient_email: string;
   rating: number;
   message: string;
   category: string;
   status: 'new' | 'reviewed';
   patient_id: string | null;
 };
 
 export type Doctor = {
   id: string;
   created_at: string;
   name: string;
   specialty: string | null;
   email: string | null;
   phone: string | null;
   is_active: boolean;
 };
 
 export type Service = {
   id: string;
   created_at: string;
   name: string;
   description: string | null;
   default_cost: number;
   category: string;
 };
 
 export type PatientService = {
   id: string;
   created_at: string;
   updated_at: string;
   patient_id: string;
   service_id: string;
   status: 'pending' | 'in_progress' | 'completed' | 'cancelled';
   assigned_cost: number;
   notes: string | null;
   scheduled_date: string | null;
   completed_date: string | null;
   service_name?: string;
   service_description?: string;
 };
 
 export type PatientFinancial = {
   id: string;
   created_at: string;
   updated_at: string;
   patient_id: string;
   total_treatment_cost: number;
   amount_paid_by_patient: number;
   remaining_from_patient: number;
   amount_due_to_doctor: number;
   notes: string | null;
 };
 
 export type Treatment = {
   id: string;
   created_at: string;
   patient_id: string;
   appointment_id: string;
   treatment_type: string;
   description: string;
   cost: number;
   status: 'planned' | 'in-progress' | 'completed';
 };
 
 export type AuthUser = {
   id: string;
   email: string;
   role: 'admin' | 'doctor' | 'staff' | 'patient';
 };
 
 export type AuthSession = {
   user: AuthUser | null;
   token: string | null;
 };
 
 // ============================================================================
 // HTTP Client Helper
 // ============================================================================
 
 interface ApiResponse<T> {
   data: T | null;
   error: string | null;
   success: boolean;
 }
 
 async function apiRequest<T>(
   endpoint: string,
   options: RequestInit = {}
 ): Promise<ApiResponse<T>> {
   try {
     const token = localStorage.getItem('auth_token');
     
     const headers: HeadersInit = {
       'Content-Type': 'application/json',
       ...(token && { 'Authorization': `Bearer ${token}` }),
       ...options.headers,
     };
 
     const response = await fetch(`${API_BASE_URL}${endpoint}`, {
       ...options,
       headers,
     });
 
     const data = await response.json();
 
     if (!response.ok) {
       return {
         data: null,
         error: data.error || `HTTP ${response.status}: ${response.statusText}`,
         success: false,
       };
     }
 
     return {
       data: data.data ?? data,
       error: null,
       success: true,
     };
   } catch (error) {
     console.error('API request failed:', error);
     return {
       data: null,
       error: error instanceof Error ? error.message : 'Network error',
       success: false,
     };
   }
 }
 
 // ============================================================================
 // Authentication Service
 // ============================================================================
 
 export const authService = {
   async signIn(email: string, password: string): Promise<ApiResponse<AuthSession>> {
     const response = await apiRequest<{ user: AuthUser; token: string }>(
       '/auth?action=signin',
       {
         method: 'POST',
         body: JSON.stringify({ email, password }),
       }
     );
 
     if (response.success && response.data?.token) {
       localStorage.setItem('auth_token', response.data.token);
       localStorage.setItem('auth_user', JSON.stringify(response.data.user));
     }
 
     return {
       ...response,
       data: response.data ? { user: response.data.user, token: response.data.token } : null,
     };
   },
 
   async signUp(email: string, password: string, name: string): Promise<ApiResponse<AuthSession>> {
     const response = await apiRequest<{ user: AuthUser; token: string }>(
       '/auth?action=signup',
       {
         method: 'POST',
         body: JSON.stringify({ email, password, name }),
       }
     );
 
     if (response.success && response.data?.token) {
       localStorage.setItem('auth_token', response.data.token);
       localStorage.setItem('auth_user', JSON.stringify(response.data.user));
     }
 
     return {
       ...response,
       data: response.data ? { user: response.data.user, token: response.data.token } : null,
     };
   },
 
   async signOut(): Promise<ApiResponse<null>> {
     const response = await apiRequest<null>('/auth?action=signout', {
       method: 'POST',
     });
 
     localStorage.removeItem('auth_token');
     localStorage.removeItem('auth_user');
 
     return response;
   },
 
   async getSession(): Promise<ApiResponse<AuthSession>> {
     const token = localStorage.getItem('auth_token');
     const userStr = localStorage.getItem('auth_user');
 
     if (!token || !userStr) {
       return { data: { user: null, token: null }, error: null, success: true };
     }
 
     // Validate token with server
     const response = await apiRequest<{ user: AuthUser }>('/auth?action=session');
 
     if (!response.success) {
       // Token invalid, clear storage
       localStorage.removeItem('auth_token');
       localStorage.removeItem('auth_user');
       return { data: { user: null, token: null }, error: null, success: true };
     }
 
     return {
       data: { user: response.data?.user || null, token },
       error: null,
       success: true,
     };
   },
 
   isAuthenticated(): boolean {
     return !!localStorage.getItem('auth_token');
   },
 
   getCurrentUser(): AuthUser | null {
     const userStr = localStorage.getItem('auth_user');
     return userStr ? JSON.parse(userStr) : null;
   },
 };
 
 // ============================================================================
 // Patient Service
 // ============================================================================
 
 export const patientService = {
   async getAll(): Promise<Patient[]> {
     console.log('🔍 Fetching patients from PHP API...');
     const response = await apiRequest<Patient[]>('/patients');
     
     if (!response.success) {
       console.error('❌ Patient fetch error:', response.error);
       throw new Error(response.error || 'Failed to fetch patients');
     }
     
     console.log('📊 Patients fetched:', response.data?.length);
     return response.data || [];
   },
 
   async getById(id: string): Promise<Patient | null> {
     const response = await apiRequest<Patient>(`/patients?id=${id}`);
     if (!response.success) throw new Error(response.error || 'Failed to fetch patient');
     return response.data;
   },
 
   async getByEmail(email: string): Promise<Patient | null> {
     const response = await apiRequest<Patient>(`/patients?email=${encodeURIComponent(email)}`);
     if (!response.success) return null;
     return response.data;
   },
 
   async create(patient: Omit<Patient, 'id' | 'created_at' | 'patient_id'>): Promise<Patient> {
     const response = await apiRequest<Patient>('/patients', {
       method: 'POST',
       body: JSON.stringify(patient),
     });
     if (!response.success) throw new Error(response.error || 'Failed to create patient');
     return response.data!;
   },
 
   async update(id: string, updates: Partial<Patient>): Promise<Patient> {
     const response = await apiRequest<Patient>(`/patients?id=${id}`, {
       method: 'PUT',
       body: JSON.stringify(updates),
     });
     if (!response.success) throw new Error(response.error || 'Failed to update patient');
     return response.data!;
   },
 
   async delete(id: string): Promise<void> {
     const response = await apiRequest<null>(`/patients?id=${id}`, {
       method: 'DELETE',
     });
     if (!response.success) throw new Error(response.error || 'Failed to delete patient');
   },
 };
 
 // ============================================================================
 // Appointment Service
 // ============================================================================
 
 export const appointmentService = {
   async getAll(): Promise<Appointment[]> {
     console.log('🔍 Fetching appointments from PHP API...');
     const response = await apiRequest<Appointment[]>('/appointments');
     
     if (!response.success) {
       console.error('❌ Appointment fetch error:', response.error);
       throw new Error(response.error || 'Failed to fetch appointments');
     }
     
     return response.data || [];
   },
 
   async getByPatientId(patientId: string): Promise<Appointment[]> {
     const response = await apiRequest<Appointment[]>(`/appointments?patient_id=${patientId}`);
     if (!response.success) throw new Error(response.error || 'Failed to fetch appointments');
     return response.data || [];
   },
 
   async getByDateAndDoctor(date: string, doctor: string): Promise<Appointment[]> {
     const response = await apiRequest<Appointment[]>(
       `/appointments?date=${date}&doctor=${encodeURIComponent(doctor)}`
     );
     if (!response.success) throw new Error(response.error || 'Failed to fetch appointments');
     return response.data || [];
   },
 
   async create(appointment: Omit<Appointment, 'id' | 'created_at'>): Promise<Appointment> {
     const response = await apiRequest<Appointment>('/appointments', {
       method: 'POST',
       body: JSON.stringify(appointment),
     });
     if (!response.success) throw new Error(response.error || 'Failed to create appointment');
     return response.data!;
   },
 
   async update(id: string, updates: Partial<Appointment>): Promise<Appointment> {
     const response = await apiRequest<Appointment>(`/appointments?id=${id}`, {
       method: 'PUT',
       body: JSON.stringify(updates),
     });
     if (!response.success) throw new Error(response.error || 'Failed to update appointment');
     return response.data!;
   },
 
   async delete(id: string): Promise<void> {
     const response = await apiRequest<null>(`/appointments?id=${id}`, {
       method: 'DELETE',
     });
     if (!response.success) throw new Error(response.error || 'Failed to delete appointment');
   },
 };
 
 // ============================================================================
 // Feedback Service
 // ============================================================================
 
 export const feedbackService = {
   async getAll(): Promise<Feedback[]> {
     console.log('🔍 Fetching feedback from PHP API...');
     const response = await apiRequest<Feedback[]>('/feedback');
     
     if (!response.success) {
       console.error('❌ Feedback fetch error:', response.error);
       throw new Error(response.error || 'Failed to fetch feedback');
     }
     
     return response.data || [];
   },
 
   async create(feedback: Omit<Feedback, 'id' | 'created_at' | 'updated_at'>): Promise<Feedback> {
     const response = await apiRequest<Feedback>('/feedback', {
       method: 'POST',
       body: JSON.stringify(feedback),
     });
     if (!response.success) throw new Error(response.error || 'Failed to create feedback');
     return response.data!;
   },
 
   async update(id: string, updates: Partial<Feedback>): Promise<Feedback> {
     const response = await apiRequest<Feedback>(`/feedback?id=${id}`, {
       method: 'PUT',
       body: JSON.stringify(updates),
     });
     if (!response.success) throw new Error(response.error || 'Failed to update feedback');
     return response.data!;
   },
 };
 
 // ============================================================================
 // Doctor Service
 // ============================================================================
 
 export const doctorService = {
   async getAll(): Promise<Doctor[]> {
     const response = await apiRequest<Doctor[]>('/doctors');
     if (!response.success) throw new Error(response.error || 'Failed to fetch doctors');
     return response.data || [];
   },
 };
 
 // ============================================================================
 // Service (Dental Services) Management
 // ============================================================================
 
 export const serviceService = {
   async getAll(): Promise<Service[]> {
     const response = await apiRequest<Service[]>('/services');
     if (!response.success) throw new Error(response.error || 'Failed to fetch services');
     return response.data || [];
   },
 };
 
 // ============================================================================
 // Patient Services (Assigned treatments)
 // ============================================================================
 
 export const patientServiceService = {
   async getByPatientId(patientId: string): Promise<PatientService[]> {
     const response = await apiRequest<PatientService[]>(`/patient-services?patient_id=${patientId}`);
     if (!response.success) throw new Error(response.error || 'Failed to fetch patient services');
     return response.data || [];
   },
 
   async create(patientService: Omit<PatientService, 'id' | 'created_at' | 'updated_at'>): Promise<PatientService> {
     const response = await apiRequest<PatientService>('/patient-services', {
       method: 'POST',
       body: JSON.stringify(patientService),
     });
     if (!response.success) throw new Error(response.error || 'Failed to create patient service');
     return response.data!;
   },
 
   async update(id: string, updates: Partial<PatientService>): Promise<PatientService> {
     const response = await apiRequest<PatientService>(`/patient-services?id=${id}`, {
       method: 'PUT',
       body: JSON.stringify(updates),
     });
     if (!response.success) throw new Error(response.error || 'Failed to update patient service');
     return response.data!;
   },
 
   async delete(id: string): Promise<void> {
     const response = await apiRequest<null>(`/patient-services?id=${id}`, {
       method: 'DELETE',
     });
     if (!response.success) throw new Error(response.error || 'Failed to delete patient service');
   },
 };
 
 // ============================================================================
 // Patient Financials
 // ============================================================================
 
 export const patientFinancialService = {
   async getByPatientId(patientId: string): Promise<PatientFinancial | null> {
     const response = await apiRequest<PatientFinancial>(`/financials?patient_id=${patientId}`);
     if (!response.success && response.error?.includes('not found')) return null;
     if (!response.success) throw new Error(response.error || 'Failed to fetch financials');
     return response.data;
   },
 
   async create(financials: Omit<PatientFinancial, 'id' | 'created_at' | 'updated_at'>): Promise<PatientFinancial> {
     const response = await apiRequest<PatientFinancial>('/financials', {
       method: 'POST',
       body: JSON.stringify(financials),
     });
     if (!response.success) throw new Error(response.error || 'Failed to create financials');
     return response.data!;
   },
 
   async update(patientId: string, updates: Partial<PatientFinancial>): Promise<PatientFinancial> {
     const response = await apiRequest<PatientFinancial>(`/financials?patient_id=${patientId}`, {
       method: 'PUT',
       body: JSON.stringify(updates),
     });
     if (!response.success) throw new Error(response.error || 'Failed to update financials');
     return response.data!;
   },
 
   async upsert(financials: Omit<PatientFinancial, 'id' | 'created_at' | 'updated_at'>): Promise<PatientFinancial> {
     // Try to get existing, then update or create
     const existing = await this.getByPatientId(financials.patient_id);
     if (existing) {
       return this.update(financials.patient_id, financials);
     }
     return this.create(financials);
   },
 };
 
 // ============================================================================
 // Treatment Service
 // ============================================================================
 
 export const treatmentService = {
   async getAll(): Promise<Treatment[]> {
     const response = await apiRequest<Treatment[]>('/treatments');
     if (!response.success) throw new Error(response.error || 'Failed to fetch treatments');
     return response.data || [];
   },
 
   async getByPatientId(patientId: string): Promise<Treatment[]> {
     const response = await apiRequest<Treatment[]>(`/treatments?patient_id=${patientId}`);
     if (!response.success) throw new Error(response.error || 'Failed to fetch treatments');
     return response.data || [];
   },
 
   async create(treatment: Omit<Treatment, 'id' | 'created_at'>): Promise<Treatment> {
     const response = await apiRequest<Treatment>('/treatments', {
       method: 'POST',
       body: JSON.stringify(treatment),
     });
     if (!response.success) throw new Error(response.error || 'Failed to create treatment');
     return response.data!;
   },
 
   async update(id: string, updates: Partial<Treatment>): Promise<Treatment> {
     const response = await apiRequest<Treatment>(`/treatments?id=${id}`, {
       method: 'PUT',
       body: JSON.stringify(updates),
     });
     if (!response.success) throw new Error(response.error || 'Failed to update treatment');
     return response.data!;
   },
 };
 
 // ============================================================================
 // Chatbot Service (Gemini AI)
 // ============================================================================
 
 export const chatbotService = {
   async chat(message: string, context?: string): Promise<string> {
     const response = await apiRequest<{ response: string }>('/chatbot', {
       method: 'POST',
       body: JSON.stringify({ message, context }),
     });
     if (!response.success) throw new Error(response.error || 'Chatbot request failed');
     return response.data?.response || 'Sorry, I could not process your request.';
   },
 };
 
 // ============================================================================
 // Health Check
 // ============================================================================
 
 export const healthService = {
   async check(): Promise<{ status: string; database: boolean; timestamp: string }> {
     const response = await apiRequest<{ status: string; database: boolean; timestamp: string }>('/health');
     if (!response.success) {
       return { status: 'error', database: false, timestamp: new Date().toISOString() };
     }
     return response.data!;
   },
 };