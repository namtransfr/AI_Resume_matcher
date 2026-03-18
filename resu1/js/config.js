/**
 * Ascenda - Configuration File
 * API Keys and Supabase Connection Configuration
 * 
 * IMPORTANT: Replace these placeholder values with your actual API keys
 */

// ============================================
// SUPABASE CONFIGURATION
// ============================================
// Location: https://supabase.com
// How to get your keys:
// 1. Go to your Supabase project
// 2. Click Settings > API in the left menu
// 3. Copy the Project URL and anon (public) key

const SUPABASE_CONFIG = {
  // Your Supabase Project URL
  // Example: "https://xyzcompany.supabase.co"
  URL: '',
  
  // Your Supabase Anon Public Key (NOT the service_role key)
  // This is safe to expose in frontend code
  // Example starts with: "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9..."
  ANON_KEY: ''
};

// ============================================
// GROQ API CONFIGURATION
// ============================================
// Location: https://console.groq.com
// How to get your API key:
// 1. Sign up at https://console.groq.com
// 2. Go to API keys in the dashboard
// 3. Create a new API key and copy it

const GROQ_CONFIG = {
  // Your Groq API Key
  // Example starts with: "gsk_..."
  // IMPORTANT: Keep this secure. In production, use environment variables
  API_KEY: '',
  
  // Groq API endpoint
  API_URL: '',
  
  // Model to use for AI analysis
  // Options: 'mixtral-8x7b-32768', 'llama2-70b-4096', etc.
  MODEL: 'llama-3.1-8b-instant'
};
window.GROQ_API_KEY = GROQ_CONFIG.API_KEY;
// ============================================
// EXPORT FOR MODULE USE
// ============================================
if (typeof module !== 'undefined' && module.exports) {
  module.exports = { SUPABASE_CONFIG, GROQ_CONFIG };
}
