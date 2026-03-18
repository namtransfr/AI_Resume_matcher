<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Submit Resume - Ascenda</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Poppins:wght@600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/styles.css">
    
    <!-- =========================================
         SUPABASE & GROQ INTEGRATION
         Load these BEFORE your custom scripts
    ========================================= -->
    <!-- Supabase JavaScript SDK -->
    <script src="https://cdn.jsdelivr.net/npm/@supabase/supabase-js@2"></script>
    
    <!-- Configuration: API Keys and endpoints -->
    <script src="js/config.js"></script>
    
    <!-- Supabase Database Manager -->
    <script src="js/supabase-manager.js"></script>
    
    <!-- Groq AI Analyzer -->
    <script src="js/groq-analyzer.js"></script>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar" id="navbar">
        <div class="nav-container">
            <a href="index.php" class="nav-logo">Ascenda</a>
            <ul class="nav-menu" id="navMenu">
                <li><a href="index.php" class="nav-link">Home</a></li>
                <li><a href="submit-resume.php" class="nav-link active">Resume</a></li>
                <li><a href="add_job.php" class="nav-link">Post Job</a></li>
                <li><a href="candidate_list.php" class="nav-link">Candidates</a></li>
                <li><a href="job_analysis.php" class="nav-link">Job Analysis</a></li>
            </ul>
            <div class="mobile-toggle" id="mobileToggle">
                <span></span>
                <span></span>
                <span></span>
            </div>
        </div>
    </nav>

    <!-- Page Header -->
    <header class="page-header">
        <h1 class="page-title">AI Resume Matcher</h1>
        <p class="page-subtitle">Submit your resume and let our AI find your perfect job match</p>
    </header>

    <!-- Form Section -->
    <section class="form-section">
        <div class="form-container">
            <!-- Display Messages -->
            <div id="messageContainer"></div>

            <div class="form-header">
                <h2>Resume Submission</h2>
                <p>Fill in your details and upload your resume</p>
            </div>

            <!-- Resume Form -->
            <form id="resumeForm" method="POST" enctype="multipart/form-data">
                <!-- NOTE: No action="php/..." needed - Using Supabase client directly -->

                <!-- Full Name -->
                <div class="form-group">
                    <label class="form-label" for="fullName">
                        Full Name <span class="required">*</span>
                    </label>
                    <input
                        type="text"
                        id="fullName"
                        name="full_name"
                        class="form-input"
                        placeholder="Enter your full name"
                        required
                    >
                </div>

                <!-- University -->
                <div class="form-group">
                    <label class="form-label" for="university">
                        University <span class="required">*</span>
                    </label>
                    <input
                        type="text"
                        id="university"
                        name="university"
                        class="form-input"
                        placeholder="Enter your university name"
                        required
                    >
                </div>

                <!-- Skills -->
                <div class="form-group">
                    <label class="form-label" for="skills">
                        Skills <span class="required">*</span>
                    </label>
                    <input
                        type="text"
                        id="skills"
                        name="skills"
                        class="form-input"
                        placeholder="Enter your skills"
                        required
                    >
                    <div class="form-help">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        <span>Separate skills with commas</span>
                    </div>
                    <div class="form-help">
                        <span>Example: Python, SQL, Machine Learning</span>
                    </div>
                </div>

                <!-- Work Experience -->
                <div class="form-group">
                    <label class="form-label" for="experienceYears">
                        Work Experience <span class="required">*</span>
                    </label>
                    <select
                        id="experienceYears"
                        name="experience_years"
                        class="form-select"
                        required
                    >
                        <option value="" disabled selected>Select years of experience</option>
                        <option value="No Experience">No Experience</option>
                        <option value="Less than 1 year">Less than 1 year</option>
                        <option value="1 year">1 year</option>
                        <option value="2 years">2 years</option>
                        <option value="3 years">3 years</option>
                        <option value="4+ years">4+ years</option>
                    </select>
                </div>

                <!-- Work Experience Details -->
                <div class="form-group">
                    <label class="form-label" for="experienceDetails">
                        Work Experience Details
                    </label>
                    <textarea
                        id="experienceDetails"
                        name="experience_details"
                        class="form-textarea"
                        placeholder="Describe your work experience..."
                    ></textarea>
                    <div class="form-help">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        <span>Example: Internship at ABC Company – Web Development. Worked on frontend and API integration</span>
                    </div>
                </div>

                <!-- Resume File Upload -->
                <div class="form-group">
                    <label class="form-label">
                        Resume File <span class="required">*</span>
                    </label>
                    <div class="file-upload" id="fileUpload">
                        <input
                            type="file"
                            id="resumeFile"
                            name="resume_file"
                            class="file-input"
                            accept=".pdf"
                            required
                        >
                        <div class="file-upload-icon">
                            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/>
                            </svg>
                        </div>
                        <div class="file-upload-text">
                            <strong>Click to upload</strong> or drag and drop
                        </div>
                        <div class="file-upload-hint">
                            PDF files only (max. 10MB)
                        </div>
                    </div>
                    <div class="file-preview" id="filePreview">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                        </svg>
                        <span id="fileName">filename.pdf</span>
                    </div>
                    <div class="form-help">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        <span>Only PDF files are allowed</span>
                    </div>
                </div>

                <!-- Submit Button -->
                <div class="form-submit">
                    <button type="submit" class="btn-primary btn-submit" id="submitBtn">
                        <span class="btn-text">Submit Resume</span>
                        <div class="spinner"></div>
                        <svg class="btn-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                    </button>
                </div>
            </form>
        </div>
    </section>

    <!-- Footer -->
    <footer class="footer">
        <p class="footer-text">&copy; 2026 Ascenda. All rights reserved. Powered by AI.</p>
    </footer>

    <script>
        // Mobile Menu Toggle
        const mobileToggle = document.getElementById('mobileToggle');
        const navMenu = document.getElementById('navMenu');

        mobileToggle.addEventListener('click', () => {
            navMenu.classList.toggle('active');
        });

        // Navbar Scroll Effect
        const navbar = document.getElementById('navbar');

        window.addEventListener('scroll', () => {
            if (window.scrollY > 50) {
                navbar.classList.add('scrolled');
            } else {
                navbar.classList.remove('scrolled');
            }
        });

        // File Upload Preview
        const fileInput = document.getElementById('resumeFile');
        const filePreview = document.getElementById('filePreview');
        const fileName = document.getElementById('fileName');
        const fileUpload = document.getElementById('fileUpload');

        fileInput.addEventListener('change', function() {
            const file = this.files[0];
            if (file) {
                // Validate file type
                if (file.type !== 'application/pdf') {
                    showMessage('Please upload a PDF file only.', 'error');
                    this.value = '';
                    return;
                }

                // Validate file size (10MB max)
                if (file.size > 10 * 1024 * 1024) {
                    showMessage('File size must be less than 10MB.', 'error');
                    this.value = '';
                    return;
                }

                fileName.textContent = file.name;
                filePreview.classList.add('show');
            }
        });

        // Drag and Drop Effects
        fileUpload.addEventListener('dragover', function(e) {
            e.preventDefault();
            this.classList.add('dragover');
        });

        fileUpload.addEventListener('dragleave', function(e) {
            e.preventDefault();
            this.classList.remove('dragover');
        });

        fileUpload.addEventListener('drop', function(e) {
            e.preventDefault();
            this.classList.remove('dragover');

            const file = e.dataTransfer.files[0];
            if (file) {
                fileInput.files = e.dataTransfer.files;
                const event = new Event('change');
                fileInput.dispatchEvent(event);
            }
        });

        // =========================================
        // SUPABASE FORM SUBMISSION
        // =========================================
        const form = document.getElementById('resumeForm');
        const submitBtn = document.getElementById('submitBtn');
        const messageContainer = document.getElementById('messageContainer');

        function showMessage(text, type) {
            const messageDiv = document.createElement('div');
            messageDiv.className = `message message-${type}`;

            const icon = type === 'success'
                ? '<svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>'
                : '<svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>';

            messageDiv.innerHTML = `${icon}<span>${text}</span>`;
            messageContainer.innerHTML = '';
            messageContainer.appendChild(messageDiv);

            // Auto-hide after 5 seconds
            setTimeout(() => {
                messageDiv.style.opacity = '0';
                setTimeout(() => messageDiv.remove(), 300);
            }, 5000);
        }

        form.addEventListener('submit', async function(e) {
            e.preventDefault();

            console.log('=== FORM SUBMISSION START ===');
            
            // Check if Supabase is initialized
            if (!supabaseManager.isInitialized()) {
                console.error('Supabase client not initialized!');
                showMessage('Error: Database connection not initialized. Check console.', 'error');
                return;
            }

            // Add loading state
            submitBtn.classList.add('loading');
            submitBtn.disabled = true;

            try {
                // Collect form data
                const applicantData = {
                    NAME: document.getElementById('fullName').value.trim(),
                    university: document.getElementById('university').value.trim(),
                    skills: document.getElementById('skills').value.trim(),
                    experience: document.getElementById('experienceYears').value
                };

                console.log('Applicant data:', applicantData);

                // Validate required fields
                if (!applicantData.NAME || !applicantData.university || !applicantData.skills) {
                    showMessage('Error: Please fill in all required fields (Name, University, Skills)', 'error');
                    submitBtn.classList.remove('loading');
                    submitBtn.disabled = false;
                    return;
                }

                // Add experience details if provided
                const experienceDetails = document.getElementById('experienceDetails').value.trim();
                if (experienceDetails) {
                    applicantData.experience = applicantData.experience + ' - ' + experienceDetails;
                }

                // Handle file upload (if file selected)
                const fileInput = document.getElementById('resumeFile');
                if (fileInput.files.length > 0) {
                    const file = fileInput.files[0];
                    
                    // Validate file type and size
                    if (file.type !== 'application/pdf') {
                        showMessage('Error: Only PDF files are allowed', 'error');
                        submitBtn.classList.remove('loading');
                        submitBtn.disabled = false;
                        return;
                    }
                    
                    if (file.size > 10 * 1024 * 1024) {
                        showMessage('Error: File size must be less than 10MB', 'error');
                        submitBtn.classList.remove('loading');
                        submitBtn.disabled = false;
                        return;
                    }
                    
                    // In production, you would upload the file to Supabase Storage
                    // For now, just store the filename
                    applicantData.resume_file = file.name;
                    console.log('Resume file:', file.name);
                }

                // Insert into Supabase
                console.log('Inserting applicant into Supabase...');
                const result = await supabaseManager.insertApplicant(applicantData);

                if (result.success) {
                    console.log('✓ Applicant inserted successfully:', result.data);
                    showMessage('✓ Resume submitted successfully! Your application is being processed.', 'success');
                    
                    // Store applicant ID for redirect if needed
                    const applicantId = result.data.id;
                    console.log('Applicant ID:', applicantId);
                    
                    // Reset form
                    form.reset();
                    filePreview.classList.remove('show');
                    
                    // Optional: Redirect to analysis page after delay
                    // setTimeout(() => {
                    //     window.location.href = `job_analysis.php?applicant_id=${applicantId}`;
                    // }, 2000);
                } else {
                    console.error('Submission failed:', result.error);
                    // show the raw error message first so developers can see it
                    showMessage(`Error: ${result.error}`, 'error');
                    
                    // Special handling for RLS/permission errors
                    if (result.code === '42501' || (result.error && result.error.toLowerCase().includes('policy'))) {
                        console.error('RLS/permission error detected:', result);
                        showMessage('Database access denied due to policy restrictions. Please contact the administrator or review Supabase RLS settings.', 'error');
                    }
                }
            } catch (error) {
                console.error('ERROR during form submission:', error);
                showMessage(`Error: ${error.message}`, 'error');
            } finally {
                submitBtn.classList.remove('loading');
                submitBtn.disabled = false;
            }
        });

        // Real-time validation feedback
        const inputs = form.querySelectorAll('.form-input, .form-select, .form-textarea');
        inputs.forEach(input => {
            input.addEventListener('blur', function() {
                if (this.hasAttribute('required') && !this.value.trim()) {
                    this.style.borderColor = 'var(--error)';
                } else {
                    this.style.borderColor = 'var(--gray-200)';
                }
            });

            input.addEventListener('input', function() {
                this.style.borderColor = 'var(--gray-200)';
            });
        });
    </script>
</body>
</html>
