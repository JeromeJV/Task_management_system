// ---------- DATABASE CONFIGURATION ----------
// Database connection config
const DB_CONFIG = {
    // For when you connect to a real database
    host: 'localhost',
    database: 'applicants_db',
    table: 'applications',
    // API endpoint (replace with your actual backend URL)
    apiEndpoint: '/api/applications',
};

// -------------------------------------------------------------------------------- API for Address

const base_url = 'https://psgc.gitlab.io/api';

const regionSelect = document.getElementById('addressRegion');
const provinceSelect = document.getElementById('addressProvince');
const citySelect = document.getElementById('addressCity');
const barangaySelect = document.getElementById('addressBarangay');
const apiError = document.getElementById('apiError');

function showApiError(show) {
    apiError.style.display = show ? 'block' : 'none';
}

function resetSelect(element, defaultText) {
    element.innerHTML = `<option value="">${defaultText}</option>`;
    element.disabled = true;
}

// Load regions on page load
window.addEventListener('DOMContentLoaded', () => {
    fetch(`${base_url}/regions/`)
        .then(res => {
            if (!res.ok) throw new Error('Network response was not ok');
            return res.json();
        })
        .then(data => {
            showApiError(false);
            data.sort((a, b) => a.name.localeCompare(b.name));
            data.forEach(region => {
                let opt = document.createElement('option');
                opt.value = region.code;
                opt.textContent = region.name;
                regionSelect.appendChild(opt);
            });
            updateProgress(); // re-check after loading
        })
        .catch(err => {
            console.warn('Could not load regions:', err);
            showApiError(true);
        });
});

// Region change -> load provinces
regionSelect.addEventListener('change', function () {
    resetSelect(provinceSelect, 'Select Province');
    resetSelect(citySelect, 'Select City/Municipality');
    resetSelect(barangaySelect, 'Select Barangay');

    if (!this.value) {
        updateProgress();
        return;
    }

    let endpoint = `${base_url}/regions/${this.value}/provinces/`;
    if (this.value === '130000000') {
        endpoint = `${base_url}/regions/${this.value}/districts/`;
    }

    fetch(endpoint)
        .then(res => {
            if (!res.ok) throw new Error('Failed to fetch provinces');
            return res.json();
        })
        .then(data => {
            showApiError(false);
            data.sort((a, b) => a.name.localeCompare(b.name));
            if (data.length === 0) {
                loadCities(this.value, 'regions');
            } else {
                provinceSelect.disabled = false;
                data.forEach(prov => {
                    let opt = document.createElement('option');
                    opt.value = prov.code;
                    opt.textContent = prov.name;
                    provinceSelect.appendChild(opt);
                });
            }
            updateProgress();
        })
        .catch(err => {
            console.warn('Could not load provinces:', err);
            showApiError(true);
            updateProgress();
        });
});

// Province change -> load cities
provinceSelect.addEventListener('change', function () {
    resetSelect(citySelect, 'Select City/Municipality');
    resetSelect(barangaySelect, 'Select Barangay');

    if (!this.value) {
        updateProgress();
        return;
    }

    let type = regionSelect.value === '130000000' ? 'districts' : 'provinces';
    loadCities(this.value, type);
});

function loadCities(parentCode, parentType) {
    fetch(`${base_url}/${parentType}/${parentCode}/cities-municipalities/`)
        .then(res => {
            if (!res.ok) throw new Error('Failed to fetch cities');
            return res.json();
        })
        .then(data => {
            showApiError(false);
            data.sort((a, b) => a.name.localeCompare(b.name));
            citySelect.disabled = false;
            data.forEach(city => {
                let opt = document.createElement('option');
                opt.value = city.code;
                opt.textContent = city.name;
                citySelect.appendChild(opt);
            });
            updateProgress();
        })
        .catch(err => {
            console.warn('Could not load cities:', err);
            showApiError(true);
            updateProgress();
        });
}

// City change -> load barangays
citySelect.addEventListener('change', function () {
    resetSelect(barangaySelect, 'Select Barangay');

    if (!this.value) {
        updateProgress();
        return;
    }

    fetch(`${base_url}/cities-municipalities/${this.value}/barangays/`)
        .then(res => {
            if (!res.ok) throw new Error('Failed to fetch barangays');
            return res.json();
        })
        .then(data => {
            showApiError(false);
            data.sort((a, b) => a.name.localeCompare(b.name));
            barangaySelect.disabled = false;
            data.forEach(brgy => {
                let opt = document.createElement('option');
                opt.value = brgy.code;
                opt.textContent = brgy.name;
                barangaySelect.appendChild(opt);
            });
            updateProgress();
        })
        .catch(err => {
            console.warn('Could not load barangays:', err);
            showApiError(true);
            updateProgress();
        });
});

// -------------------------------------------------------------------------------- VALIDATION FUNCTIONS

function showError(field, message) {
    const container = field.closest('.field');
    const existingError = container.querySelector('.field-error');
    if (existingError) existingError.remove();
    
    const errorSpan = document.createElement('span');
    errorSpan.className = 'field-error';
    errorSpan.textContent = message;
    container.appendChild(errorSpan);
    field.classList.add('error');
}

function clearError(field) {
    const container = field.closest('.field');
    const error = container.querySelector('.field-error');
    if (error) error.remove();
    field.classList.remove('error');
}

function isEmpty(field) {
    if (field.type === 'file') {
        return !field.files || field.files.length === 0;
    }
    if (field.tagName === 'SELECT') {
        return !field.value || field.value.trim() === '';
    }
    return !field.value || field.value.trim() === '';
}

// -------------------------------------------------------------------------------- VALIDATION RULES 

function validateRequired(field) {
    if (field.hasAttribute('data-required') && isEmpty(field)) {
        const label = field.closest('.field').querySelector('label').textContent;
        return `${label} is required`;
    }
    return null;
}

function validateName(field) {
    const value = field.value.trim();
    if (!value) return null;
    
    if (value.length < 2) {
        return 'Name must be at least 2 characters';
    }
    if (!/^[a-zA-Z\s\-'.,]+$/.test(value)) {
        return 'Name contains invalid characters';
    }
    return null;
}

function validatePhone(field) {
    const value = field.value.replace(/[\s\-\(\)]/g, '');
    if (!value) return null;
    
    const phoneRegex = /^(09|\+639|639)\d{9}$/;
    if (!phoneRegex.test(value)) {
        return 'Enter valid PH number (e.g., 09123456789)';
    }
    return null;
}

function validateEmail(field) {
    const value = field.value.trim();
    if (!value) return null;
    
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    if (!emailRegex.test(value)) {
        return 'Enter a valid email address';
    }
    return null;
}

function validateUrl(field) {
    const value = field.value.trim();
    if (!value) return null;
    
    try {
        const url = new URL(value);
        if (!url.hostname.includes('facebook.com')) {
            return 'Enter a valid Facebook URL';
        }
    } catch {
        return 'Enter a valid URL (https://...)';
    }
    return null;
}

function validateDate(field) {
    const value = field.value;
    if (!value) return null;
    
    const date = new Date(value);
    if (isNaN(date.getTime())) {
        return 'Enter a valid date';
    }
    
    const label = field.closest('.field').querySelector('label').textContent;
    
    if (label.includes('available')) {
        const yesterday = new Date();
        yesterday.setDate(yesterday.getDate() - 1);
        if (date <= yesterday) {
            return 'Date must be today or in the future';
        }
    }
    
    if (label.includes('Start date') || label.includes('End date')) {
        if (date > new Date()) {
            return 'Date cannot be in the future';
        }
    }
    
    return null;
}

function validateForm() {
    let isValid = true;
    const fields = document.querySelectorAll('.card input, .card select, .card textarea');
    
    fields.forEach(field => {
        const error = validateField(field);
        if (error) {
            showError(field, error);
            isValid = false;
        } else {
            clearError(field);
        }
    });
    
    const dates = document.querySelectorAll('input[type="date"]');
    if (dates.length >= 2) {
        const startDate = dates[dates.length - 2];
        const endDate = dates[dates.length - 1];
        
        if (startDate.value && endDate.value) {
            if (new Date(endDate.value) < new Date(startDate.value)) {
                showError(endDate, 'End date must be after start date');
                isValid = false;
            }
        }
    }
    
    return isValid;
}

function validateFile(field) {
    if (!field.files || field.files.length === 0) return null;
    
    const file = field.files[0];
    const allowedTypes = ['application/pdf', 'application/msword', 
                          'application/vnd.openxmlformats-officedocument.wordprocessingml.document'];
    
    if (!allowedTypes.includes(file.type)) {
        return 'Only PDF or Word files allowed';
    }
    
    if (file.size > 5 * 1024 * 1024) {
        return 'File must be less than 5MB';
    }
    
    return null;
}

function validateField(field) {
    if (field.offsetParent === null) return null;
    
    let error = validateRequired(field);
    if (error) return error;
    
    if (isEmpty(field)) return null;
    
    const type = field.type;
    const label = field.closest('.field').querySelector('label').textContent;
    
    if (label.includes('Name')) return validateName(field);
    if (type === 'tel' || label.includes('Phone')) return validatePhone(field);
    if (type === 'email') return validateEmail(field);
    if (type === 'url') return validateUrl(field);
    if (type === 'date') return validateDate(field);
    if (type === 'file') return validateFile(field);
    
    return null;
}

// -------------------------------------------------------------------------------- PROGRESS LOGIC 

function isFieldFilled(field) {
    if (!field) return false;
    if (field.tagName === 'SELECT') {
        return field.value && field.value.trim() !== '';
    } else if (field.type === 'file') {
        return field.files && field.files.length > 0;
    } else {
        return field.value && field.value.trim() !== '';
    }
}

function isSectionComplete(section) {
    const required = section.querySelectorAll('[data-required]');
    for (let el of required) {
        if (!isFieldFilled(el)) return false;
    }
    return true;
}

function updateProgress() {
    const cards = document.querySelectorAll('.card');
    const steps = document.querySelectorAll('.step');

    cards.forEach((card, index) => {
        const step = steps[index];
        if (!step) return;
        
        step.classList.remove('active', 'done');
        if (isSectionComplete(card)) {
            step.classList.add('done');
        }
    });

    let foundActive = false;
    steps.forEach((step) => {
        if (!step.classList.contains('done') && !foundActive) {
            step.classList.add('active');
            foundActive = true;
        }
    });
}

// -------------------------------------------------------------------------------- DATABASE-READY DATA COLLECTION -

function collectFormData() {
    // This structure maps directly to your database table columns
    const formData = {
        // Contact Information
        last_name: document.querySelector('fieldset.name input:nth-child(1)')?.value?.trim() || '',
        first_name: document.querySelector('fieldset.name input:nth-child(2)')?.value?.trim() || '',
        middle_name: document.querySelector('fieldset.name input:nth-child(3)')?.value?.trim() || '',
        
        // Contacts
        phone_number: document.querySelector('fieldset.contacts input[type="tel"]')?.value?.trim() || '',
        email: document.querySelector('fieldset.contacts input[type="email"]')?.value?.trim() || '',
        facebook_link: document.querySelector('fieldset.contacts input[type="url"]')?.value?.trim() || '',
        
        // Address
        region: document.getElementById('region')?.value || '',
        province: document.getElementById('province')?.value || '',
        city: document.getElementById('city')?.value || '',
        barangay: document.getElementById('barangay')?.value || '',
        street: document.querySelector('fieldset.address input[type="text"]:nth-of-type(1)')?.value?.trim() || '',
        house_number: document.querySelector('fieldset.address input[type="text"]:nth-of-type(2)')?.value?.trim() || '',
        
        // Job History - Position
        position_applying: document.querySelector('.position-information input')?.value?.trim() || '',
        
        // Work Experience
        company_name: document.querySelector('.work-experience input:nth-of-type(1)')?.value?.trim() || '',
        previous_position: document.querySelector('.work-experience input:nth-of-type(2)')?.value?.trim() || '',
        years_of_stay: document.querySelector('.work-experience input[inputmode="numeric"]')?.value?.trim() || '',
        start_date: document.querySelectorAll('.work-experience input[type="date"]')[0]?.value || '',
        end_date: document.querySelectorAll('.work-experience input[type="date"]')[1]?.value || '',
        
        // Education
        highest_education: document.querySelector('.education select')?.value || '',
        
        // Availability
        availability_date: document.querySelector('[data-section="3"] input[type="date"]')?.value || '',
        resume_filename: document.getElementById('resumeFile')?.files[0]?.name || '',
        
        // Metadata
        submitted_at: new Date().toISOString(),
        status: 'pending',
        ip_address: '' // Will be filled by backend
    };
    
    return formData;
}

// -------------------------------------------------------------------------------- DATABASE SUBMISSION 

async function submitToDatabase(formData) {
    // SIMULATION: This is where you'll connect to your real database
    // Replace this entire function with your actual API call
    
    // OPTION 1: Using fetch to send to your backend API
    /*
    const response = await fetch(DB_CONFIG.apiEndpoint, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify(formData)
    });
    
    if (!response.ok) {
        throw new Error('Failed to submit application');
    }
    
    return await response.json();
    */
    
    // OPTION 2: If using FormData (for file upload)
    /*
    const formDataToSend = new FormData();
    for (const [key, value] of Object.entries(formData)) {
        formDataToSend.append(key, value);
    }
    
    // Add the actual file
    const fileInput = document.getElementById('resumeFile');
    if (fileInput.files[0]) {
        formDataToSend.append('resume_file', fileInput.files[0]);
    }
    
    const response = await fetch(DB_CONFIG.apiEndpoint, {
        method: 'POST',
        body: formDataToSend
    });
    */
    
    // For now, simulate database save
    return new Promise((resolve) => {
        setTimeout(() => {
            // Log what would be saved to database
            console.log('📦 DATA READY FOR DATABASE:', formData);
            
            // Here's the SQL that would run on your backend:
            console.log(`
                -- SQL for your database:
                INSERT INTO ${DB_CONFIG.table} (
                    last_name, first_name, middle_name,
                    phone_number, email, facebook_link,
                    region, province, city, barangay, street, house_number,
                    position_applying,
                    company_name, previous_position, years_of_stay, start_date, end_date,
                    highest_education,
                    availability_date, resume_filename,
                    submitted_at, status
                ) VALUES (
                    '${formData.last_name}', '${formData.first_name}', '${formData.middle_name}',
                    '${formData.phone_number}', '${formData.email}', '${formData.facebook_link}',
                    '${formData.region}', '${formData.province}', '${formData.city}', '${formData.barangay}', '${formData.street}', '${formData.house_number}',
                    '${formData.position_applying}',
                    '${formData.company_name}', '${formData.previous_position}', '${formData.years_of_stay}', '${formData.start_date}', '${formData.end_date}',
                    '${formData.highest_education}',
                    '${formData.availability_date}', '${formData.resume_filename}',
                    '${formData.submitted_at}', '${formData.status}'
                );
            `);
            
            resolve({ 
                success: true, 
                id: 'APP-' + Date.now(),
                message: 'Application saved to database' 
            });
        }, 1500);
    });
}

// -------------------------------------------------------------------------------- POPUP CONFIRMATION NG SUBMIT

function createPopupModal() {
    // Create modal if it doesn't exist
    if (document.getElementById('successModal')) return;
    
    const modal = document.createElement('div');
    modal.id = 'successModal';
    modal.innerHTML = `
        <div class="modal-overlay"></div>
        <div class="modal-content">
            <div class="modal-icon">✓</div>
            <h2>Application Submitted!</h2>
            <p>Your application has been successfully submitted.</p>
            <p class="modal-reference">Reference ID: <span id="referenceId"></span></p>
            <p class="modal-message">We will review your application and contact you soon.</p>
            <button class="modal-close-btn" onclick="closeModal()">OK</button>
        </div>
    `;
    
    document.body.appendChild(modal);
    
    // Add modal styles
    const modalStyles = document.createElement('style');
    modalStyles.textContent = `
        #successModal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: 9999;
        }
        
        #successModal.show {
            display: block;
        }
        
        .modal-overlay {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.6);
            animation: fadeIn 0.3s ease;
        }
        
        .modal-content {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background: white;
            padding: 40px;
            border-radius: 12px;
            text-align: center;
            min-width: 400px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            animation: slideUp 0.4s ease;
        }
        
        .modal-icon {
            width: 80px;
            height: 80px;
            background: #28a745;
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 40px;
            margin: 0 auto 20px;
            animation: scaleIn 0.5s ease 0.2s both;
        }
        
        .modal-content h2 {
            color: #28a745;
            margin: 0 0 10px;
            font-size: 24px;
        }
        
        .modal-content p {
            color: #666;
            margin: 5px 0;
            font-size: 16px;
        }
        
        .modal-reference {
            font-weight: bold;
            color: #333 !important;
            margin: 15px 0 !important;
            padding: 10px;
            background: #f8f9fa;
            border-radius: 6px;
        }
        
        #referenceId {
            color: #007bff;
            font-family: monospace;
            font-size: 18px;
        }
        
        .modal-message {
            font-style: italic;
            margin-bottom: 20px !important;
        }
        
        .modal-close-btn {
            background: #28a745;
            color: white;
            border: none;
            padding: 12px 40px;
            border-radius: 6px;
            font-size: 16px;
            cursor: pointer;
            transition: background 0.3s;
            margin-top: 10px;
        }
        
        .modal-close-btn:hover {
            background: #218838;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }
        
        @keyframes slideUp {
            from { 
                opacity: 0;
                transform: translate(-50%, -40%);
            }
            to { 
                opacity: 1;
                transform: translate(-50%, -50%);
            }
        }
        
        @keyframes scaleIn {
            from { 
                transform: scale(0);
            }
            to { 
                transform: scale(1);
            }
        }
    `;
    
    document.head.appendChild(modalStyles);
}

function showModal(referenceId) {
    const modal = document.getElementById('successModal');
    document.getElementById('referenceId').textContent = referenceId;
    modal.classList.add('show');
}

function closeModal() {
    const modal = document.getElementById('successModal');
    modal.classList.remove('show');
}

// -------------------------------------------------------------------------------- RESET FORM 

function resetForm() {
    const form = document.getElementById('applicationForm');
    
    // Reset all inputs
    form.reset();
    
    // Clear all error messages
    document.querySelectorAll('.field-error').forEach(error => error.remove());
    document.querySelectorAll('.error').forEach(field => field.classList.remove('error'));
    
    // Clear file name display
    const fileName = document.getElementById('resumeFileName');
    if (fileName) fileName.textContent = '';
    
    // Reset select dropdowns to first option
    document.querySelectorAll('select').forEach(select => {
        select.selectedIndex = 0;
    });
    
    // Disable dependent dropdowns
    ['province', 'city', 'barangay'].forEach(id => {
        const select = document.getElementById(id);
        if (select) select.disabled = true;
    });
    
    // Reset progress
    updateProgress();
    
    // Reset submit button
    const submitBtn = document.getElementById('submitBtn');
    submitBtn.disabled = false;
    submitBtn.textContent = 'Submit Application';
    
    const submitStatus = document.getElementById('submitStatus');
    submitStatus.textContent = '';
    
    // Scroll to top
    window.scrollTo({ top: 0, behavior: 'smooth' });
}

// -------------------------------------------------------------------------------- FORM SUBMISSION HANDLER

async function handleFormSubmit(e) {
    e.preventDefault();
    
    const submitBtn = document.getElementById('submitBtn');
    const submitStatus = document.getElementById('submitStatus');
    
    // Validate form
    if (!validateForm()) {
        submitStatus.textContent = 'Please fix the errors above';
        submitStatus.style.color = '#dc3545';
        
        const firstError = document.querySelector('.error');
        if (firstError) {
            firstError.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }
        return;
    }
    
    // Disable button during submission
    submitBtn.disabled = true;
    submitBtn.textContent = 'Submitting...';
    submitStatus.textContent = '';
    
    try {
        // Collect data in database-ready format
        const formData = collectFormData();
        
        // Submit to database
        const result = await submitToDatabase(formData);
        
        // Show success popup
        createPopupModal();
        showModal(result.id);
        
        // Reset form after showing modal
        setTimeout(() => {
            resetForm();
        }, 500);
        
        console.log('✅ Application saved successfully:', result);
        
    } catch (error) {
        console.error('❌ Submission failed:', error);
        submitStatus.textContent = 'Failed to submit. Please try again.';
        submitStatus.style.color = '#dc3545';
        submitBtn.disabled = false;
        submitBtn.textContent = 'Submit Application';
    }
}

// -------------------------------------------------------------------------------- EVENT LISTENERS

document.querySelectorAll('input, select, textarea').forEach(field => {
    field.addEventListener('blur', function() {
        const error = validateField(this);
        if (error) {
            showError(this, error);
        } else {
            clearError(this);
        }
    });
    
    field.addEventListener('input', function() {
        clearError(this);
        updateProgress();
    });
    
    field.addEventListener('change', function() {
        clearError(this);
        updateProgress();
    });
});

document.getElementById('applicationForm').addEventListener('submit', handleFormSubmit);

document.getElementById('resumeFile')?.addEventListener('change', function() {
    const fileName = document.getElementById('resumeFileName');
    if (this.files && this.files[0]) {
        const size = (this.files[0].size / (1024 * 1024)).toFixed(2);
        fileName.textContent = `Selected: ${this.files[0].name} (${size} MB)`;
    } else {
        fileName.textContent = '';
    }
    updateProgress();
});

// Add error styles
const style = document.createElement('style');
style.textContent = `
    .field-error {
        display: block;
        color: #dc3545;
        font-size: 0.85rem;
        margin-top: 4px;
    }
    
    input.error, select.error {
        border: 2px solid #dc3545 !important;
        background-color: #fff5f5 !important;
    }
    
    .submit-status {
        margin-left: 15px;
        font-weight: 500;
    }
`;
document.head.appendChild(style);

// Initialize
createPopupModal();
updateProgress();