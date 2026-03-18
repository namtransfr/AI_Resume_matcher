/**
 * Ascenda - Supabase Database Utilities
 * Handles all database operations with Supabase
 */

// Import configuration
// Make sure config.js is loaded in your HTML before this script

class SupabaseManager {
  constructor() {
    // Initialize Supabase client
    // The supabase global is available from the Supabase JavaScript SDK
    // You need to load it: <script src="https://cdn.jsdelivr.net/npm/@supabase/supabase-js@2"></script>
    
    if (typeof supabase === 'undefined') {
      console.error('ERROR: Supabase client not initialized. Make sure to load the Supabase SDK before this script.');
      this.client = null;
      return;
    }
    
    try {
      // Create Supabase client instance
      this.client = supabase.createClient(
        SUPABASE_CONFIG.URL,
        SUPABASE_CONFIG.ANON_KEY
      );
      console.log('✓ Supabase client initialized successfully');
    } catch (error) {
      console.error('ERROR initializing Supabase:', error);
      this.client = null;
    }
  }

  /**
   * Check if client is initialized
   */
  isInitialized() {
    return this.client !== null;
  }

  /**
   * Insert a new applicant into the 'applicants' table
   * 
   * Columns in the applicants table:
   * - NAME (text) - Applicant's full name
   * - university (text) - University name
   * - skills (text) - Comma-separated skills
   * - experience (text) - Years/description of experience
   * - resume_file (text) - Resume file path or name (optional)
   * - created_at (timestamp) - Automatically set by Supabase
   * 
   * @param {Object} applicantData - The applicant data
   * @returns {Promise<Object>} - Response with inserted data or error
   */
  async insertApplicant(applicantData) {
    if (!this.isInitialized()) {
      console.error('ERROR: Supabase client not initialized');
      return { success: false, error: 'Supabase not initialized' };
    }

    try {
      console.log('Inserting applicant:', applicantData);
      
      const { data, error } = await this.client
        .from('applicants')
        .insert([applicantData])
        .select();

      if (error) {
        // Log everything about the error so developers can inspect it
        console.error('Supabase insert error:', error);
        console.error('Supabase insert error (stringified):', JSON.stringify(error, null, 2));
        
        // RLS / permission error (Postgres 42501) often means a policy is blocking the
        // insert on the client role (anon). We surface a clearer message.
        if (error.code === '42501' || (error.message && error.message.toLowerCase().includes('permission'))) {
          return {
            success: false,
            error: 'Database policy prevents saving. Check Supabase Row Level Security (RLS) settings.',
            code: error.code || '42501',
            details: error
          };
        }
        
        return {
          success: false,
          // Propagate the actual message so the UI can display it if needed
          error: `Database error: ${error.message}`,
          details: error
        };
      }

      console.log('✓ Applicant inserted successfully:', data);
      return {
        success: true,
        data: data[0],
        message: 'Applicant data saved successfully'
      };
    } catch (error) {
      console.error('ERROR inserting applicant:', error);
      return {
        success: false,
        error: error.message
      };
    }
  }

  /**
   * Fetch all applicants
   * 
   * @param {Object} options - Query options (limit, offset, etc.)
   * @returns {Promise<Object>} - Array of applicants or error
   */
  async fetchApplicants(options = {}) {
    if (!this.isInitialized()) {
      console.error('ERROR: Supabase client not initialized');
      return { success: false, error: 'Supabase not initialized', data: [] };
    }

    try {
      let query = this.client
        .from('applicants')
        .select('*')
        .order('created_at', { ascending: false });

      // Apply filters if provided
      if (options.limit) {
        query = query.limit(options.limit);
      }

      const { data, error } = await query;

      if (error) {
        console.error('Supabase fetch error:', error);
        return {
          success: false,
          error: `Failed to fetch applicants: ${error.message}`,
          data: []
        };
      }

      console.log(`✓ Fetched ${data.length} applicants`);
      return {
        success: true,
        data: data || [],
        count: data?.length || 0
      };
    } catch (error) {
      console.error('ERROR fetching applicants:', error);
      return {
        success: false,
        error: error.message,
        data: []
      };
    }
  }

  /**
   * Fetch a specific applicant by ID
   * 
   * @param {number} applicantId - The applicant ID
   * @returns {Promise<Object>} - Applicant data or error
   */
  async fetchApplicantById(applicantId) {
    if (!this.isInitialized()) {
      console.error('ERROR: Supabase client not initialized');
      return { success: false, error: 'Supabase not initialized' };
    }

    // ensure we have a number for logging/debug
    const idNum = Number(applicantId);

    try {
      // primary key on our table is "applicant_id" (no plain "id" column)
      const { data, error } = await this.client
        .from('applicants')
        .select('*')
        .eq('id', idNum)
        .single();

      if (error) {
        console.error('Supabase fetch error:', error);
        return {
          success: false,
          error: `Applicant not found: ${error.message}`
        };
      }

      console.log('✓ Fetched applicant:', data);
      return {
        success: true,
        data: data
      };
    } catch (error) {
      console.error('ERROR fetching applicant:', error);
      return {
        success: false,
        error: error.message
      };
    }
  }

  /**
   * Fetch all jobs
   * 
   * @param {Object} options - Query options
   * @returns {Promise<Object>} - Array of jobs or error
   */
  async fetchJobs(options = {}) {
    if (!this.isInitialized()) {
      console.error('ERROR: Supabase client not initialized');
      return { success: false, error: 'Supabase not initialized', data: [] };
    }

    try {
      let query = this.client
        .from('jobs')
        .select('*')
        .order('created_at', { ascending: false });

      if (options.limit) {
        query = query.limit(options.limit);
      }

      const { data, error } = await query;

      if (error) {
        console.error('Supabase fetch error:', error);
        return {
          success: false,
          error: `Failed to fetch jobs: ${error.message}`,
          data: []
        };
      }

      console.log(`✓ Fetched ${data.length} jobs`);
      return {
        success: true,
        data: data || [],
        count: data?.length || 0
      };
    } catch (error) {
      console.error('ERROR fetching jobs:', error);
      return {
        success: false,
        error: error.message,
        data: []
      };
    }
  }

  /**
   * Fetch a specific job by ID
   * 
   * @param {number} jobId - The job ID
   * @returns {Promise<Object>} - Job data or error
   */
  async fetchJobById(jobId) {
    if (!this.isInitialized()) {
      console.error('ERROR: Supabase client not initialized');
      return { success: false, error: 'Supabase not initialized' };
    }

    try {
      const { data, error } = await this.client
        .from('jobs')
        .select('*')
        .eq('id', jobId)
        .single();

      if (error) {
        console.error('Supabase fetch error:', error);
        return {
          success: false,
          error: `Job not found: ${error.message}`
        };
      }

      console.log('✓ Fetched job:', data);
      return {
        success: true,
        data: data
      };
    } catch (error) {
      console.error('ERROR fetching job:', error);
      return {
        success: false,
        error: error.message
      };
    }
  }

  /**
   * Insert a new job posting
   * 
   * Columns in the jobs table:
   * - position_name (text) - Job title
   * - required_skills (text) - Comma-separated required skills
   * - job_description (text) - Full job description
   * - created_at (timestamp) - Automatically set by Supabase
   * 
   * @param {Object} jobData - The job data
   * @returns {Promise<Object>} - Response with inserted data or error
   */
  async insertJob(jobData) {
    if (!this.isInitialized()) {
      console.error('ERROR: Supabase client not initialized');
      return { success: false, error: 'Supabase not initialized' };
    }

    try {
      console.log('Inserting job:', jobData);
      
      const { data, error } = await this.client
        .from('jobs')
        .insert([jobData])
        .select();

      if (error) {
        console.error('Supabase insert error:', error);
        console.error('Supabase insert error (stringified):', JSON.stringify(error, null, 2));
        
        if (error.code === '42501' || (error.message && error.message.toLowerCase().includes('permission'))) {
          return {
            success: false,
            error: 'Database policy prevents saving. Check Supabase Row Level Security (RLS) configuration.',
            code: error.code || '42501',
            details: error
          };
        }
        
        return {
          success: false,
          error: `Database error: ${error.message}`,
          details: error
        };
      }

      console.log('✓ Job inserted successfully:', data);
      return {
        success: true,
        data: data[0],
        message: 'Job posted successfully'
      };
    } catch (error) {
      console.error('ERROR inserting job:', error);
      return {
        success: false,
        error: error.message
      };
    }
  }

  /**
   * Delete an applicant
   * 
   * @param {number} applicantId - The applicant ID to delete
   * @returns {Promise<Object>} - Success or error
   */
  async deleteApplicant(applicantId) {
    if (!this.isInitialized()) {
      console.error('ERROR: Supabase client not initialized');
      return { success: false, error: 'Supabase not initialized' };
    }

    try {
      console.log('Deleting applicant:', applicantId);
      
      const { error } = await this.client
        .from('applicants')
        .delete()
        .eq('id', applicantId);

      if (error) {
        console.error('Supabase delete error:', error);
        return {
          success: false,
          error: `Failed to delete applicant: ${error.message}`
        };
      }

      console.log('✓ Applicant deleted successfully');
      return {
        success: true,
        message: 'Applicant deleted successfully'
      };
    } catch (error) {
      console.error('ERROR deleting applicant:', error);
      return {
        success: false,
        error: error.message
      };
    }
  }

  /**
   * Update an applicant
   * 
   * @param {number} applicantId - The applicant ID
   * @param {Object} updateData - The data to update
   * @returns {Promise<Object>} - Updated data or error
   */
  async updateApplicant(applicantId, updateData) {
    if (!this.isInitialized()) {
      console.error('ERROR: Supabase client not initialized');
      return { success: false, error: 'Supabase not initialized' };
    }

    try {
      console.log('Updating applicant:', applicantId, updateData);
      
      const { data, error } = await this.client
        .from('applicants')
        .update(updateData)
        .eq('id', applicantId)
        .select();

      if (error) {
        console.error('Supabase update error:', error);
        return {
          success: false,
          error: `Failed to update applicant: ${error.message}`
        };
      }

      console.log('✓ Applicant updated successfully:', data);
      return {
        success: true,
        data: data[0],
        message: 'Applicant updated successfully'
      };
    } catch (error) {
      console.error('ERROR updating applicant:', error);
      return {
        success: false,
        error: error.message
      };
    }
  }
}

// Create a global instance
const supabaseManager = new SupabaseManager();

console.log('✓ Supabase Manager loaded');
