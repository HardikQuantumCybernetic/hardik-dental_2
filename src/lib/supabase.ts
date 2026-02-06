/**
 * ============================================================================
 * Supabase/API Service Layer
 * ============================================================================
 * This file provides a unified API layer that works with both:
 * 1. Supabase (for Lovable development/preview)
 * 2. PHP Backend (for VPS production deployment)
 * 
 * The USE_PHP_BACKEND flag controls which backend to use.
 * ============================================================================
 */

import { supabase } from '@/integrations/supabase/client'

// Backend mode flag - set to true for VPS/PHP deployment
const USE_PHP_BACKEND = import.meta.env.VITE_USE_PHP_BACKEND === 'true';
const API_BASE_URL = import.meta.env.VITE_API_URL || 'http://localhost:8000/api';

// ============================================================================
// Types
// ============================================================================

export type Doctor = {
  id: string
  created_at: string
  name: string
  specialty: string | null
  email: string | null
  phone: string | null
  is_active: boolean
}

export type Feedback = {
  id: string
  created_at: string
  updated_at: string
  patient_name: string
  patient_email: string
  rating: number
  message: string
  category: string
  status: 'new' | 'reviewed'
  patient_id: string | null
}

export type Service = {
  id: string
  created_at: string
  name: string
  description: string | null
  default_cost: number
  category: string
}

export type PatientService = {
  id: string
  created_at: string
  updated_at: string
  patient_id: string
  service_id: string
  status: 'pending' | 'in_progress' | 'completed' | 'cancelled'
  assigned_cost: number
  notes: string | null
  scheduled_date: string | null
  completed_date: string | null
  service_name?: string
  service_description?: string
}

export type PatientFinancial = {
  id: string
  created_at: string
  updated_at: string
  patient_id: string
  total_treatment_cost: number
  amount_paid_by_patient: number
  remaining_from_patient: number
  amount_due_to_doctor: number
  notes: string | null
}

export type Patient = {
  id: string
  created_at: string
  name: string
  email: string
  phone: string
  date_of_birth: string
  address: string
  medical_history: string
  insurance_info: string
  status: 'active' | 'inactive'
  patient_id: string | null
}

export type Appointment = {
  id: string
  created_at: string
  patient_id: string
  appointment_date: string
  appointment_time: string
  service_type: string
  doctor: string
  status: 'scheduled' | 'confirmed' | 'completed' | 'cancelled' | 'no-show'
  notes: string
}

export type Treatment = {
  id: string
  created_at: string
  patient_id: string
  appointment_id: string
  treatment_type: string
  description: string
  cost: number
  status: 'planned' | 'in-progress' | 'completed'
}

// Re-export supabase for components that still need direct access
export { supabase }

// ============================================================================
// PHP API Helper (for VPS deployment)
// ============================================================================

async function phpApiRequest<T>(
  endpoint: string,
  options: RequestInit = {}
): Promise<T> {
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
    throw new Error(data.error || `HTTP ${response.status}: ${response.statusText}`);
  }

  return data.data ?? data;
}

// ============================================================================
// Patient Service
// ============================================================================

export const patientService = {
  async getAll(): Promise<Patient[]> {
    console.log('🔍 Fetching patients...');
    
    if (USE_PHP_BACKEND) {
      return phpApiRequest<Patient[]>('/patients');
    }
    
    const { data: authData } = await supabase.auth.getUser();
    console.log('🔐 Auth status:', authData?.user ? 'Authenticated' : 'Not authenticated');
    
    const { data, error } = await supabase
      .from('patients')
      .select('*')
      .order('created_at', { ascending: false });
    
    console.log('📊 Patients query result:', { data, error, count: data?.length });
    
    if (error) {
      console.error('❌ Patient fetch error:', error);
      throw error;
    }
    return data as Patient[];
  },

  async create(patient: Omit<Patient, 'id' | 'created_at' | 'patient_id'>): Promise<Patient> {
    if (USE_PHP_BACKEND) {
      return phpApiRequest<Patient>('/patients', {
        method: 'POST',
        body: JSON.stringify(patient),
      });
    }
    
    const { data, error } = await supabase
      .from('patients')
      .insert([patient])
      .select()
      .single();
    
    if (error) throw error;
    return data as Patient;
  },

  async update(id: string, updates: Partial<Patient>): Promise<Patient> {
    if (USE_PHP_BACKEND) {
      return phpApiRequest<Patient>(`/patients?id=${id}`, {
        method: 'PUT',
        body: JSON.stringify(updates),
      });
    }
    
    const { data, error } = await supabase
      .from('patients')
      .update(updates)
      .eq('id', id)
      .select()
      .single();
    
    if (error) throw error;
    return data as Patient;
  },

  async delete(id: string): Promise<void> {
    if (USE_PHP_BACKEND) {
      await phpApiRequest<null>(`/patients?id=${id}`, { method: 'DELETE' });
      return;
    }
    
    const { error } = await supabase
      .from('patients')
      .delete()
      .eq('id', id);
    
    if (error) throw error;
  },

  async getByEmail(email: string): Promise<Patient | null> {
    if (USE_PHP_BACKEND) {
      try {
        return await phpApiRequest<Patient>(`/patients?email=${encodeURIComponent(email)}`);
      } catch {
        return null;
      }
    }
    
    const { data, error } = await supabase
      .from('patients')
      .select('*')
      .eq('email', email)
      .single();
    
    if (error && error.code !== 'PGRST116') throw error;
    return data as Patient | null;
  }
};

// ============================================================================
// Appointment Service
// ============================================================================

export const appointmentService = {
  async getAll(): Promise<Appointment[]> {
    console.log('🔍 Fetching appointments...');
    
    if (USE_PHP_BACKEND) {
      return phpApiRequest<Appointment[]>('/appointments');
    }
    
    try {
      const { data: authData } = await supabase.auth.getUser();
      console.log('🔐 Auth status for appointments:', authData?.user ? 'Authenticated' : 'Not authenticated');
      
      const { data: appointments, error: appointmentsError } = await supabase
        .from('appointments')
        .select('*')
        .order('appointment_date', { ascending: true });
      
      console.log('📊 Appointments query result:', { appointments, appointmentsError, count: appointments?.length });
      
      if (appointmentsError) {
        console.error('Appointments error:', appointmentsError);
        throw appointmentsError;
      }

      const { data: patients, error: patientsError } = await supabase
        .from('patients')
        .select('id, name, email, phone');
      
      if (patientsError) {
        console.error('Patients error:', patientsError);
        throw patientsError;
      }

      const appointmentsWithPatients = appointments?.map(appointment => {
        const patient = patients?.find(p => p.id === appointment.patient_id);
        return {
          ...appointment,
          patient_name: patient?.name || 'Unknown Patient',
          patient_email: patient?.email,
          patient_phone: patient?.phone
        };
      }) || [];

      console.log('Merged appointments data:', appointmentsWithPatients);
      return appointmentsWithPatients as (Appointment & { patient_name: string; patient_email?: string; patient_phone?: string })[];
    } catch (error) {
      console.error('Error in appointmentService.getAll:', error);
      throw error;
    }
  },

  async create(appointment: Omit<Appointment, 'id' | 'created_at'>): Promise<Appointment> {
    if (USE_PHP_BACKEND) {
      return phpApiRequest<Appointment>('/appointments', {
        method: 'POST',
        body: JSON.stringify(appointment),
      });
    }
    
    const { data, error } = await supabase
      .from('appointments')
      .insert([appointment])
      .select()
      .single();
    
    if (error) throw error;
    return data as Appointment;
  },

  async update(id: string, updates: Partial<Appointment>): Promise<Appointment> {
    if (USE_PHP_BACKEND) {
      return phpApiRequest<Appointment>(`/appointments?id=${id}`, {
        method: 'PUT',
        body: JSON.stringify(updates),
      });
    }
    
    const { data, error } = await supabase
      .from('appointments')
      .update(updates)
      .eq('id', id)
      .select()
      .single();
    
    if (error) throw error;
    return data as Appointment;
  },

  async delete(id: string): Promise<void> {
    if (USE_PHP_BACKEND) {
      await phpApiRequest<null>(`/appointments?id=${id}`, { method: 'DELETE' });
      return;
    }
    
    const { error } = await supabase
      .from('appointments')
      .delete()
      .eq('id', id);
    
    if (error) throw error;
  },

  async getByDateAndDoctor(date: string, doctor: string): Promise<Appointment[]> {
    if (USE_PHP_BACKEND) {
      return phpApiRequest<Appointment[]>(
        `/appointments?date=${date}&doctor=${encodeURIComponent(doctor)}`
      );
    }
    
    const { data, error } = await supabase
      .from('appointments')
      .select('*')
      .eq('appointment_date', date)
      .eq('doctor', doctor)
      .neq('status', 'cancelled');
    
    if (error) throw error;
    return data as Appointment[];
  }
};

// ============================================================================
// Feedback Service
// ============================================================================

export const feedbackService = {
  async getAll(): Promise<Feedback[]> {
    console.log('🔍 Fetching feedback...');
    
    if (USE_PHP_BACKEND) {
      return phpApiRequest<Feedback[]>('/feedback');
    }
    
    const { data, error } = await supabase
      .from('feedback')
      .select('*')
      .order('created_at', { ascending: false });
    
    console.log('📊 Feedback query result:', { data, error, count: data?.length });
    
    if (error) {
      console.error('❌ Feedback fetch error:', error);
      throw error;
    }
    return data as Feedback[];
  },

  async create(feedback: Omit<Feedback, 'id' | 'created_at' | 'updated_at'>): Promise<Feedback> {
    if (USE_PHP_BACKEND) {
      return phpApiRequest<Feedback>('/feedback', {
        method: 'POST',
        body: JSON.stringify(feedback),
      });
    }
    
    const { data, error } = await supabase
      .from('feedback')
      .insert([feedback])
      .select()
      .single();
    
    if (error) throw error;
    return data as Feedback;
  },

  async update(id: string, updates: Partial<Feedback>): Promise<Feedback> {
    if (USE_PHP_BACKEND) {
      return phpApiRequest<Feedback>(`/feedback?id=${id}`, {
        method: 'PUT',
        body: JSON.stringify(updates),
      });
    }
    
    const { data, error } = await supabase
      .from('feedback')
      .update(updates)
      .eq('id', id)
      .select()
      .single();
    
    if (error) throw error;
    return data as Feedback;
  },

  async delete(id: string): Promise<void> {
    if (USE_PHP_BACKEND) {
      await phpApiRequest<null>(`/feedback?id=${id}`, { method: 'DELETE' });
      return;
    }
    
    const { error } = await supabase
      .from('feedback')
      .delete()
      .eq('id', id);
    
    if (error) throw error;
  }
};

// ============================================================================
// Doctor Service
// ============================================================================

export const doctorService = {
  async getAll(): Promise<Doctor[]> {
    if (USE_PHP_BACKEND) {
      return phpApiRequest<Doctor[]>('/doctors');
    }
    
    const { data, error } = await supabase
      .from('doctors')
      .select('*')
      .eq('is_active', true)
      .order('name', { ascending: true });
    
    if (error) throw error;
    return data as Doctor[];
  }
};

// ============================================================================
// Service Management
// ============================================================================

export const serviceService = {
  async getAll(): Promise<Service[]> {
    if (USE_PHP_BACKEND) {
      return phpApiRequest<Service[]>('/services');
    }
    
    const { data, error } = await supabase
      .from('services')
      .select('*')
      .order('name', { ascending: true });
    
    if (error) throw error;
    return data as Service[];
  }
};

// ============================================================================
// Patient Services (Todo List)
// ============================================================================

export const patientServiceService = {
  async getByPatientId(patientId: string): Promise<PatientService[]> {
    if (USE_PHP_BACKEND) {
      return phpApiRequest<PatientService[]>(`/patient-services?patient_id=${patientId}`);
    }
    
    const { data, error } = await supabase
      .from('patient_services')
      .select(`
        *,
        service:services(name, description)
      `)
      .eq('patient_id', patientId)
      .order('created_at', { ascending: false });
    
    if (error) throw error;
    
    return data.map(item => ({
      ...item,
      service_name: item.service?.name,
      service_description: item.service?.description
    })) as PatientService[];
  },

  async create(patientService: Omit<PatientService, 'id' | 'created_at' | 'updated_at'>): Promise<PatientService> {
    if (USE_PHP_BACKEND) {
      return phpApiRequest<PatientService>('/patient-services', {
        method: 'POST',
        body: JSON.stringify(patientService),
      });
    }
    
    const { data, error } = await supabase
      .from('patient_services')
      .insert([patientService])
      .select()
      .single();
    
    if (error) throw error;
    return data as PatientService;
  },

  async update(id: string, updates: Partial<PatientService>): Promise<PatientService> {
    if (USE_PHP_BACKEND) {
      return phpApiRequest<PatientService>(`/patient-services?id=${id}`, {
        method: 'PUT',
        body: JSON.stringify(updates),
      });
    }
    
    const { data, error } = await supabase
      .from('patient_services')
      .update(updates)
      .eq('id', id)
      .select()
      .single();
    
    if (error) throw error;
    return data as PatientService;
  },

  async delete(id: string): Promise<void> {
    if (USE_PHP_BACKEND) {
      await phpApiRequest<null>(`/patient-services?id=${id}`, { method: 'DELETE' });
      return;
    }
    
    const { error } = await supabase
      .from('patient_services')
      .delete()
      .eq('id', id);
    
    if (error) throw error;
  }
};

// ============================================================================
// Patient Financials Service
// ============================================================================

export const patientFinancialService = {
  async getByPatientId(patientId: string): Promise<PatientFinancial | null> {
    if (USE_PHP_BACKEND) {
      try {
        return await phpApiRequest<PatientFinancial>(`/financials?patient_id=${patientId}`);
      } catch {
        return null;
      }
    }
    
    const { data, error } = await supabase
      .from('patient_financials')
      .select('*')
      .eq('patient_id', patientId)
      .single();
    
    if (error && error.code !== 'PGRST116') throw error;
    return data as PatientFinancial | null;
  },

  async create(financials: Omit<PatientFinancial, 'id' | 'created_at' | 'updated_at'>): Promise<PatientFinancial> {
    if (USE_PHP_BACKEND) {
      return phpApiRequest<PatientFinancial>('/financials', {
        method: 'POST',
        body: JSON.stringify(financials),
      });
    }
    
    const { data, error } = await supabase
      .from('patient_financials')
      .insert([financials])
      .select()
      .single();
    
    if (error) throw error;
    return data as PatientFinancial;
  },

  async update(patientId: string, updates: Partial<PatientFinancial>): Promise<PatientFinancial> {
    if (USE_PHP_BACKEND) {
      return phpApiRequest<PatientFinancial>(`/financials?patient_id=${patientId}`, {
        method: 'PUT',
        body: JSON.stringify(updates),
      });
    }
    
    const { data, error } = await supabase
      .from('patient_financials')
      .update(updates)
      .eq('patient_id', patientId)
      .select()
      .single();
    
    if (error) throw error;
    return data as PatientFinancial;
  },

  async upsert(financials: Omit<PatientFinancial, 'id' | 'created_at' | 'updated_at'>): Promise<PatientFinancial> {
    if (USE_PHP_BACKEND) {
      // Try to get existing first, then create or update
      const existing = await this.getByPatientId(financials.patient_id);
      if (existing) {
        return this.update(financials.patient_id, financials);
      }
      return this.create(financials);
    }
    
    const { data, error } = await supabase
      .from('patient_financials')
      .upsert([financials], { onConflict: 'patient_id' })
      .select()
      .single();
    
    if (error) throw error;
    return data as PatientFinancial;
  }
};

// ============================================================================
// Treatment Service
// ============================================================================

export const treatmentService = {
  async getAll(): Promise<Treatment[]> {
    if (USE_PHP_BACKEND) {
      return phpApiRequest<Treatment[]>('/treatments');
    }
    
    const { data, error } = await supabase
      .from('treatments')
      .select('*')
      .order('created_at', { ascending: false });
    
    if (error) throw error;
    return data as Treatment[];
  },

  async create(treatment: Omit<Treatment, 'id' | 'created_at'>): Promise<Treatment> {
    if (USE_PHP_BACKEND) {
      return phpApiRequest<Treatment>('/treatments', {
        method: 'POST',
        body: JSON.stringify(treatment),
      });
    }
    
    const { data, error } = await supabase
      .from('treatments')
      .insert([treatment])
      .select()
      .single();
    
    if (error) throw error;
    return data as Treatment;
  },

  async update(id: string, updates: Partial<Treatment>): Promise<Treatment> {
    if (USE_PHP_BACKEND) {
      return phpApiRequest<Treatment>(`/treatments?id=${id}`, {
        method: 'PUT',
        body: JSON.stringify(updates),
      });
    }
    
    const { data, error } = await supabase
      .from('treatments')
      .update(updates)
      .eq('id', id)
      .select()
      .single();
    
    if (error) throw error;
    return data as Treatment;
  },

  async delete(id: string): Promise<void> {
    if (USE_PHP_BACKEND) {
      await phpApiRequest<null>(`/treatments?id=${id}`, { method: 'DELETE' });
      return;
    }
    
    const { error } = await supabase
      .from('treatments')
      .delete()
      .eq('id', id);
    
    if (error) throw error;
  }
};

// ============================================================================
// Chatbot Service (for AI integration)
// ============================================================================

export const chatbotService = {
  async sendMessage(message: string): Promise<string> {
    if (USE_PHP_BACKEND) {
      const response = await phpApiRequest<{ response: string }>('/chatbot', {
        method: 'POST',
        body: JSON.stringify({ message }),
      });
      return response.response;
    }
    
    // Use Supabase Edge Function
    const { data, error } = await supabase.functions.invoke('chat-with-gemini', {
      body: { message }
    });
    
    if (error) throw error;
    return data.response;
  }
};
