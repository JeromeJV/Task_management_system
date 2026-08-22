// ---------- DATABASE CONFIGURATION ----------
const DB_CONFIG = {
    host: 'localhost',
    database: 'applicants_db',
    table: 'applications',
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
            updateFormState();
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
        updateFormState();
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
            updateFormState();
        })
        .catch(err => {
            console.warn('Could not load provinces:', err);
            showApiError(true);
            updateFormState();
        });
});

// Province change -> load cities
provinceSelect.addEventListener('change', function () {
    resetSelect(citySelect, 'Select City/Municipality');
    resetSelect(barangaySelect, 'Select Barangay');

    if (!this.value) {
        updateFormState();
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
            updateFormState();
        })
        .catch(err => {
            console.warn('Could not load cities:', err);
            showApiError(true);
            updateFormState();
        });
}

// City change -> load barangays
citySelect.addEventListener('change', function () {
    resetSelect(barangaySelect, 'Select Barangay');

    if (!this.value) {
        updateFormState();
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
            updateFormState();
        })
        .catch(err => {
            console.warn('Could not load barangays:', err);
            showApiError(true);
            updateFormState();
        });
});

// -------------------------------------------------------------------------------- STEP LOCKING SYSTEM

function lockStep(stepNumber) {
    const card = document.querySelector(`.card[data-section="${stepNumber}"]`);
    if (!card) return;
    
    if (!card.classList.contains('locked')) {
        card.classList.add('locked');
        card.style.opacity = '0.5';
        card.style.pointerEvents = 'none';
        card.style.userSelect = 'none';
        
        // Add lock message
        let lockMessage = card.querySelector('.lock-message');
        if (!lockMessage) {
            lockMessage = document.createElement('div');
            lockMessage.className = 'lock-message';
            lockMessage.innerHTML = `
                <p>Complete previous section to unlock</p>
            `;
            card.appendChild(lockMessage);
        }
    }
}

function unlockStep(stepNumber) {
    const card = document.querySelector(`.card[data-section="${stepNumber}"]`);
    if (!card) return;
    
    if (card.classList.contains('locked')) {
        card.classList.remove('locked');
        card.style.opacity = '1';
        card.style.pointerEvents = 'auto';
        card.style.userSelect = 'auto';
        
        // Remove lock message
        const lockMessage = card.querySelector('.lock-message');
        if (lockMessage) lockMessage.remove();
    }
}

function updateStepLocks() {
    // Check if step 1 is complete
    const step1Complete = isSectionComplete(1);
    
    // Lock/unlock steps based on previous step completion
    if (step1Complete) {
        unlockStep(2);
    } else {
        lockStep(2);
        lockStep(3);
        lockStep(4);
    }
    
    // Check if step 2 is complete
    const step2Complete = isSectionComplete(2);
    if (step2Complete && step1Complete) {
        unlockStep(3);
    } else {
        lockStep(3);
    }
    
    // Check if step 3 is complete
    const step3Complete = isSectionComplete(3);
    if (step3Complete && step2Complete && step1Complete) {
        unlockStep(4);
    } else {
        lockStep(4);
    }
}

// -------------------------------------------------------------------------------- VALIDATION FUNCTIONS

function showError(field, message) {
    const container = field.closest('.field');
    if (!container) return;
    
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
    if (!container) return;
    
    const error = container.querySelector('.field-error');
    if (error) error.remove();
    field.classList.remove('error');
}

function isEmpty(field) {
    if (!field) return true;
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
        const labelElement = field.closest('.field')?.querySelector('label');
        const label = labelElement ? labelElement.textContent : 'This field';
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

function validateSchoolName(field) {
    const value = field.value.trim();
    if (!value) return null;
    
    if (value.length < 3) {
        return 'School name must be at least 3 characters';
    }
    if (value.length > 100) {
        return 'School name must be less than 100 characters';
    }
    if (!/^[a-zA-Z0-9\s\-'.,&()]+$/.test(value)) {
        return 'School name contains invalid characters';
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
    
    const labelElement = field.closest('.field')?.querySelector('label');
    const label = labelElement ? labelElement.textContent : '';
    
    if (label.includes('available')) {
        const today = new Date();
        today.setHours(0, 0, 0, 0);
        const selectedDate = new Date(value);
        selectedDate.setHours(0, 0, 0, 0);
        
        if (selectedDate < today) {
            return 'Date must be today or in the future';
        }
    }
    
    if (label.includes('Start date') || label.includes('End date')) {
        const today = new Date();
        today.setHours(0, 0, 0, 0);
        const selectedDate = new Date(value);
        selectedDate.setHours(0, 0, 0, 0);
        
        if (selectedDate > today) {
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
    
    // Validate date ranges
    const startDate = document.getElementById('workStartDate');
    const endDate = document.getElementById('workEndDate');
    
    if (startDate && endDate && startDate.value && endDate.value) {
        if (new Date(endDate.value) < new Date(startDate.value)) {
            showError(endDate, 'End date must be after start date');
            isValid = false;
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
    if (!field || field.offsetParent === null) return null;
    
    let error = validateRequired(field);
    if (error) return error;
    
    if (isEmpty(field)) return null;
    
    const type = field.type;
    const labelElement = field.closest('.field')?.querySelector('label');
    const label = labelElement ? labelElement.textContent : '';
    const fieldId = field.id;
    
    if (fieldId === 'schoolName') return validateSchoolName(field);
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

function isSectionComplete(sectionNumber) {
    const section = document.querySelector(`.card[data-section="${sectionNumber}"]`);
    if (!section) return false;
    
    const requiredFields = section.querySelectorAll('[data-required]');
    for (let field of requiredFields) {
        if (field.disabled) continue; // Skip disabled fields
        if (!isFieldFilled(field)) return false;
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
        if (isSectionComplete(index + 1)) {
            step.classList.add('done');
        } else {
            // Find the first incomplete step
            const previousComplete = index === 0 || isSectionComplete(index);
            if (previousComplete) {
                step.classList.add('active');
            }
        }
    });
}

// Single function to update everything without recursion
function updateFormState() {
    updateProgress();
    updateStepLocks();
}

// -------------------------------------------------------------------------------- DATABASE-READY DATA COLLECTION

function collectFormData() {
    const formData = {
        // Contact Information
        last_name: document.getElementById('lastName')?.value?.trim() || '',
        first_name: document.getElementById('firstName')?.value?.trim() || '',
        middle_name: document.getElementById('middleName')?.value?.trim() || '',
        
        // Contacts
        phone_number: document.getElementById('phoneNumber')?.value?.trim() || '',
        email: document.getElementById('emailAddress')?.value?.trim() || '',
        facebook_link: document.getElementById('facebookUrl')?.value?.trim() || '',
        
        // Address
        region: document.getElementById('addressRegion')?.value || '',
        province: document.getElementById('addressProvince')?.value || '',
        city: document.getElementById('addressCity')?.value || '',
        barangay: document.getElementById('addressBarangay')?.value || '',
        street: document.getElementById('addressStreet')?.value?.trim() || '',
        house_number: document.getElementById('addressHouseNumber')?.value?.trim() || '',
        
        // Job History - Position
        position_applying: document.getElementById('positionApplying')?.value?.trim() || '',
        
        // Work Experience
        company_name: document.getElementById('workCompany')?.value?.trim() || '',
        previous_position: document.getElementById('workPosition')?.value?.trim() || '',
        start_date: document.getElementById('workStartDate')?.value || '',
        end_date: document.getElementById('workEndDate')?.value || '',
        
        // Education
        school_name: document.getElementById('schoolName')?.value?.trim() || '',
        highest_education: document.getElementById('educationLevel')?.value || '',

        // Availability
        availability_date: document.getElementById('availabilityDate')?.value || '',
        resume_filename: document.getElementById('resumeFile')?.files[0]?.name || '',
        
        // Metadata
        submitted_at: new Date().toISOString(),
        status: 'pending',
        ip_address: ''
    };
    
    return formData;
}

// -------------------------------------------------------------------------------- DATABASE SUBMISSION 

async function submitToDatabase(formData) {
    // SIMULATION: This is where you'll connect to your real database
    
    // Uncomment and use this for actual API call:
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
    
    // For now, simulate database save
    return new Promise((resolve) => {
        setTimeout(() => {
            console.log('📦 DATA READY FOR DATABASE:', formData);
            
            console.log(`
                -- SQL for your database:
                INSERT INTO ${DB_CONFIG.table} (
                    last_name, first_name, middle_name,
                    phone_number, email, facebook_link,
                    region, province, city, barangay, street, house_number,
                    position_applying,
                    company_name, previous_position, start_date, end_date,
                    school_name, highest_education,
                    availability_date, resume_filename,
                    submitted_at, status
                ) VALUES (
                    '${formData.last_name}', '${formData.first_name}', '${formData.middle_name}',
                    '${formData.phone_number}', '${formData.email}', '${formData.facebook_link}',
                    '${formData.region}', '${formData.province}', '${formData.city}', '${formData.barangay}', 
                    '${formData.street}', '${formData.house_number}',
                    '${formData.position_applying}',
                    '${formData.company_name}', '${formData.previous_position}', 
                    '${formData.start_date}', '${formData.end_date}',
                    '${formData.school_name}', '${formData.highest_education}',
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

// -------------------------------------------------------------------------------- POPUP CONFIRMATION

function createPopupModal() {
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
    if (modal) modal.classList.remove('show');
}

// -------------------------------------------------------------------------------- RESET FORM 

function resetForm() {
    const form = document.getElementById('applicationForm');
    
    form.reset();
    
    document.querySelectorAll('.field-error').forEach(error => error.remove());
    document.querySelectorAll('.error').forEach(field => field.classList.remove('error'));
    
    const fileName = document.getElementById('resumeFileName');
    if (fileName) fileName.textContent = '';
    
    document.querySelectorAll('select').forEach(select => {
        select.selectedIndex = 0;
    });
    
    ['addressProvince', 'addressCity', 'addressBarangay'].forEach(id => {
        const select = document.getElementById(id);
        if (select) select.disabled = true;
    });
    
    // Reset step locks
    updateFormState();
    
    const submitBtn = document.getElementById('submitBtn');
    submitBtn.disabled = false;
    submitBtn.textContent = 'Submit Application';
    
    const submitStatus = document.getElementById('submitStatus');
    submitStatus.textContent = '';
    
    window.scrollTo({ top: 0, behavior: 'smooth' });
}

// -------------------------------------------------------------------------------- FORM SUBMISSION HANDLER

async function handleFormSubmit(e) {
    e.preventDefault();
    
    const submitBtn = document.getElementById('submitBtn');
    const submitStatus = document.getElementById('submitStatus');
    
    // Validate entire form
    if (!validateForm()) {
        submitStatus.textContent = 'Please fix the errors above';
        submitStatus.style.color = '#dc3545';
        
        const firstError = document.querySelector('.error');
        if (firstError) {
            firstError.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }
        return;
    }
    
    submitBtn.disabled = true;
    submitBtn.textContent = 'Submitting...';
    submitStatus.textContent = '';
    
    try {
        const formData = collectFormData();
        const result = await submitToDatabase(formData);
        
        createPopupModal();
        showModal(result.id);
        
        setTimeout(() => {
            resetForm();
        }, 500);
        
        console.log('Application saved successfully:', result);
        
    } catch (error) {
        console.error('Submission failed:', error);
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
        updateFormState();
    });
    
    field.addEventListener('change', function() {
        clearError(this);
        updateFormState();
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
    updateFormState();
});

// Add error styles and lock styles
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
    
    .card.locked {
        position: relative;
        pointer-events: none;
        user-select: none;
        opacity: 0.5;
        transition: opacity 0.3s ease;
    }
    
    .lock-message {
        position: absolute;
        top: 50%;
        left: 50%;
        transform: translate(-50%, -50%);
        background: rgba(255, 255, 255, 0.95);
        padding: 20px;
        border-radius: 8px;
        text-align: center;
        box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        z-index: 10;
        pointer-events: auto;
    }
    
    .lock-icon {
        font-size: 32px;
        margin-bottom: 10px;
    }
    
    .lock-message p {
        margin: 0;
        color: #666;
        font-size: 14px;
        font-weight: 500;
    }
`;

document.head.appendChild(style);

// Initialize
createPopupModal();
updateFormState();