/**
 * Ascenda - Groq AI Analysis Engine
 * Performs AI Resume Matching using Groq API
 * 
 * NOTE: Make sure config.js is loaded before this script
 * <script src="js/config.js"></script>
 */

class GroqAIAnalyzer {
  constructor() {
    console.log("GROQ CONFIG:", GROQ_CONFIG);

    this.apiUrl = GROQ_CONFIG.API_URL;
    this.apiKey = GROQ_CONFIG.API_KEY;
    this.model = GROQ_CONFIG.MODEL;

    console.log("API KEY:", this.apiKey);

    console.log('✓ Groq AI Analyzer initialized');
    console.log('  Model:', this.model);
    console.log('  API Endpoint:', this.apiUrl);
}

  /**
   * Validate that API key is configured
   */
  isConfigured() {
    if (!this.apiKey || this.apiKey.trim() === '') {
      console.warn('⚠ WARNING: Groq API key appears to be placeholder. Set your real API key in js/config.js');
      return false;
    }
    return true;
  }

  /**
   * Call Groq API for AI analysis
   * 
   * @param {string} prompt - The prompt to send to AI
   * @returns {Promise<Object>} - AI response or error
   */
  async callGroqAPI(prompt) {
    if (!this.isConfigured()) {
      console.error('ERROR: Groq API key not configured');
      return {
        success: false,
        error: 'Groq API key not configured. Please set your API key in js/config.js'
      };
    }

    try {
      console.log('Calling Groq API for AI analysis...');
      
      const response = await fetch(this.apiUrl, {
        method: 'POST',
        headers: {
          'Authorization': `Bearer ${this.apiKey}`,
          'Content-Type': 'application/json'
        },
        body: JSON.stringify({
          model: this.model,
          messages: [
            {
              role: 'user',
              content: prompt
            }
          ],
          temperature: 0.7,
          max_tokens: 1024,
          top_p: 1
        })
      });

      if (!response.ok) {
        const errorData = await response.json();
        console.error('Groq API error:', errorData);
        
        if (response.status === 401) {
          return {
            success: false,
            error: 'Unauthorized: Invalid Groq API key. Check your configuration.'
          };
        }
        
        return {
          success: false,
          error: `Groq API error: ${errorData.error?.message || response.statusText}`
        };
      }

      const result = await response.json();
      
      if (!result.choices || !result.choices[0] || !result.choices[0].message) {
        console.error('Unexpected Groq response format:', result);
        return {
          success: false,
          error: 'Invalid response format from Groq API'
        };
      }

      const aiResponse = result.choices[0].message.content;
      console.log('✓ Groq API response received');
      
      return {
        success: true,
        response: aiResponse,
        model: result.model,
        usage: result.usage
      };
    } catch (error) {
      console.error('ERROR calling Groq API:', error);
      return {
        success: false,
        error: error.message
      };
    }
  }

  /**
   * Analyze candidate resume against job requirements using AI
   * 
   * @param {Object} candidate - Candidate data {name, skills, experience}
   * @param {Object} job - Job data {position_name, required_skills, job_description}
   * @returns {Promise<Object>} - Analysis result with score, strengths, weaknesses, recommendation
   */
  async analyzeResumeMatch(candidate, job) {
    if (!candidate || !job) {
      console.error('ERROR: Invalid candidate or job data');
      return {
        success: false,
        error: 'Missing candidate or job data'
      };
    }

    try {
      console.log('Starting AI analysis...');
      console.log('Candidate:', candidate.NAME);
      console.log('Position:', job.position_name);

      // Create detailed prompt for AI analysis
      const prompt = `You are an expert recruiter evaluating a candidate for a job position. Analyze the following data and provide a structured assessment.

CANDIDATE PROFILE:
- Name: ${candidate.NAME || 'N/A'}
- University: ${candidate.university || 'N/A'}
- Skills: ${candidate.skills || 'N/A'}
- Experience: ${candidate.experience || 'Not specified'}

JOB REQUIREMENTS:
- Position: ${job.position_name || 'N/A'}
- Required Skills: ${job.required_skills || 'N/A'}
- Job Description: ${job.job_description || 'N/A'}

Please provide a structured analysis in the following JSON format:
{
  "match_score": <0-100>,
  "strengths": ["strength1", "strength2", "strength3"],
  "weaknesses": ["weakness1", "weakness2"],
  "recommendation": "HIGHLY RECOMMENDED | RECOMMENDED | CONSIDER | NOT RECOMMENDED",
  "analysis_summary": "2-3 sentences explaining the overall fit"
}

Base your analysis on:
1. Skill overlap between candidate and position
2. Relevance of experience to job requirements
3. Education background alignment
4. Overall career progression suitability

Return ONLY the JSON object, no additional text.`;

      // Call Groq API
      const result = await this.callGroqAPI(prompt);
      
      if (!result.success) {
        return result;
      }

      // Parse AI response
      try {
        // Extract JSON from response (in case there's extra text)
        const jsonMatch = result.response.match(/\{[\s\S]*\}/);
        const jsonStr = jsonMatch ? jsonMatch[0] : result.response;
        
        const analysis = JSON.parse(jsonStr);
        
        console.log('✓ AI Analysis completed successfully');
        
        return {
          success: true,
          analysis: analysis,
          raw_response: result.response
        };
      } catch (parseError) {
        console.error('Failed to parse AI response as JSON:', parseError);
        console.log('Raw response:', result.response);
        
        // Fallback: create structured response from text
        return {
          success: false,
          error: 'Failed to parse AI analysis. Response was not in expected format.',
          raw_response: result.response
        };
      }
    } catch (error) {
      console.error('ERROR in analyzeResumeMatch:', error);
      return {
        success: false,
        error: error.message
      };
    }
  }

  /**
   * Calculate a simple fallback match score (if AI fails)
   * This provides basic skill-based matching as backup
   * 
   * @param {Object} candidate - Candidate data
   * @param {Object} job - Job data
   * @returns {Object} - Fallback analysis object
   */
  calculateFallbackScore(candidate, job) {
    try {
      const candidateSkills = (candidate.skills || '')
        .toLowerCase()
        .split(',')
        .map(s => s.trim())
        .filter(s => s);
      
      const jobSkills = (job.required_skills || '')
        .toLowerCase()
        .split(',')
        .map(s => s.trim())
        .filter(s => s);

      const matchedSkills = candidateSkills.filter(skill => 
        jobSkills.some(jobSkill => jobSkill.includes(skill) || skill.includes(jobSkill))
      );

      const score = jobSkills.length > 0
        ? Math.round((matchedSkills.length / jobSkills.length) * 100)
        : 0;

      const strengths = matchedSkills.length > 0
        ? [`Has experience in: ${matchedSkills.join(', ')}`]
        : ['Profile submitted and reviewed'];

      const missingSkills = jobSkills.filter(skill => !matchedSkills.includes(skill));
      const weaknesses = missingSkills.length > 0
        ? [`Missing required skills: ${missingSkills.join(', ')}`]
        : [];

      let recommendation = 'NOT RECOMMENDED';
      if (score >= 75) {
        recommendation = 'HIGHLY RECOMMENDED';
      } else if (score >= 50) {
        recommendation = 'RECOMMENDED';
      } else if (score >= 25) {
        recommendation = 'CONSIDER';
      }

      return {
        match_score: score,
        strengths: strengths,
        weaknesses: weaknesses,
        recommendation: recommendation,
        analysis_summary: `Candidate has ${matchedSkills.length} of ${jobSkills.length} required skills. ${missingSkills.length > 0 ? 'Missing skills: ' + missingSkills.slice(0, 2).join(', ') : 'Good fit for the position.'}`
      };
    } catch (error) {
      console.error('ERROR in fallback calculation:', error);
      return {
        match_score: 0,
        strengths: [],
        weaknesses: ['Unable to perform analysis'],
        recommendation: 'NOT RECOMMENDED',
        analysis_summary: 'Unable to complete analysis'
      };
    }
  }
}

// Create a global instance
const groqAnalyzer = new GroqAIAnalyzer();

console.log('✓ Groq AI Analyzer loaded');
