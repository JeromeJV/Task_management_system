// ==========================================
// PHILIPPINE ADDRESS API (PSGC) INTEGRATION
// ==========================================

// Base URL para sa PSGC GitLab API
const base_url = 'https://psgc.gitlab.io/api';

// Kuhanin ang mga DOM Elements para sa dropdowns at errors
const regionSelect = document.getElementById('addressRegion');
const provinceSelect = document.getElementById('addressProvince');
const citySelect = document.getElementById('addressCity');
const barangaySelect = document.getElementById('addressBarangay');
const apiError = document.getElementById('apiError');

// Helper function: Ipakita o itago ang error message kung pumalya ang API
function showApiError(show) {
    if (apiError) apiError.style.display = show ? 'block' : 'none';
}

// Helper function: I-clear at i-disable ang dropdown kapag binago ang parent selection
function resetSelect(element, defaultText) {
    element.innerHTML = `<option value="">${defaultText}</option>`;
    element.disabled = true;
}

// ------------------------------------------
// STEP 1: I-load ang listahan ng Regions kapag nag-load ang page
// ------------------------------------------
window.addEventListener('DOMContentLoaded', () => {
    fetch(`${base_url}/regions.json`)
        .then(res => {
            if (!res.ok) throw new Error('Network response was not ok');
            return res.json();
        })
        .then(data => {
            showApiError(false);
            // I-sort ang mga rehiyon mula A hanggang Z
            data.sort((a, b) => a.name.localeCompare(b.name));
            
            // I-populate ang Region Dropdown
            data.forEach(region => {
                let opt = document.createElement('option');
                opt.value = region.name;        // Pangalan ng rehiyon ang maipapadala sa PHP form submit
                opt.dataset.code = region.code; // PSGC Code na gagamitin sa pag-fetch ng Province/City
                opt.textContent = region.name;  // Pangalang lalabas sa dropdown UI
                regionSelect.appendChild(opt);
            });
            if (typeof updateFormState === 'function') updateFormState();
        })
        .catch(err => {
            console.warn('Could not load regions:', err);
            showApiError(true);
        });
});

// ------------------------------------------
// STEP 2: Kapag pumili ng Region -> I-load ang Provinces (o Cities kung NCR)
// ------------------------------------------
regionSelect.addEventListener('change', function () {
    const selectedOption = this.options[this.selectedIndex];
    const regionCode = selectedOption.dataset.code; // Kuhanin ang nakagagap na API code

    // I-reset muna ang mga kasunod na dropdowns
    resetSelect(provinceSelect, 'Select Province');
    resetSelect(citySelect, 'Select City/Municipality');
    resetSelect(barangaySelect, 'Select Barangay');

    if (!regionCode) return; // Kapag binalik sa "Select Region", huminto na rito

    // Subukang kuhanin ang mga lalawigan sa napiling rehiyon
    fetch(`${base_url}/regions/${regionCode}/provinces.json`)
        .then(res => res.json())
        .then(data => {
            // TANDAAN: Ang NCR ay walang Province, kaya dideretso tayo sa Cities
            if (data.length === 0) {
                loadCitiesFromRegion(regionCode);
            } else {
                provinceSelect.disabled = false; // I-enable ang province dropdown
                data.sort((a, b) => a.name.localeCompare(b.name));
                data.forEach(province => {
                    let opt = document.createElement('option');
                    opt.value = province.name;
                    opt.dataset.code = province.code;
                    opt.textContent = province.name;
                    provinceSelect.appendChild(opt);
                });
            }
        })
        .catch(() => showApiError(true));
});

// Special Function: Para sa mga rehiyong walang probinsya (tulad ng NCR/Metro Manila)
function loadCitiesFromRegion(regionCode) {
    fetch(`${base_url}/regions/${regionCode}/cities-municipalities.json`)
        .then(res => res.json())
        .then(data => {
            citySelect.disabled = false;
            data.sort((a, b) => a.name.localeCompare(b.name));
            data.forEach(city => {
                let opt = document.createElement('option');
                opt.value = city.name;
                opt.dataset.code = city.code;
                opt.textContent = city.name;
                citySelect.appendChild(opt);
            });
        })
        .catch(() => showApiError(true));
}

// ------------------------------------------
// STEP 3: Kapag pumili ng Province -> I-load ang Cities / Municipalities
// ------------------------------------------
provinceSelect.addEventListener('change', function () {
    const selectedOption = this.options[this.selectedIndex];
    const provinceCode = selectedOption.dataset.code;

    resetSelect(citySelect, 'Select City/Municipality');
    resetSelect(barangaySelect, 'Select Barangay');

    if (!provinceCode) return;

    fetch(`${base_url}/provinces/${provinceCode}/cities-municipalities.json`)
        .then(res => res.json())
        .then(data => {
            citySelect.disabled = false;
            data.sort((a, b) => a.name.localeCompare(b.name));
            data.forEach(city => {
                let opt = document.createElement('option');
                opt.value = city.name;
                opt.dataset.code = city.code;
                opt.textContent = city.name;
                citySelect.appendChild(opt);
            });
        })
        .catch(() => showApiError(true));
});

// ------------------------------------------
// STEP 4: Kapag pumili ng City -> I-load ang Barangays
// ------------------------------------------
citySelect.addEventListener('change', function () {
    const selectedOption = this.options[this.selectedIndex];
    const cityCode = selectedOption.dataset.code;

    resetSelect(barangaySelect, 'Select Barangay');

    if (!cityCode) return;

    fetch(`${base_url}/cities-municipalities/${cityCode}/barangays.json`)
        .then(res => res.json())
        .then(data => {
            barangaySelect.disabled = false;
            data.sort((a, b) => a.name.localeCompare(b.name));
            data.forEach(barangay => {
                let opt = document.createElement('option');
                opt.value = barangay.name;
                opt.textContent = barangay.name;
                barangaySelect.appendChild(opt);
            });
        })
        .catch(() => showApiError(true));
});

// ------------------------------------------
// UTILITY: Reset Function para sa Form
// ------------------------------------------
function resetForm() {
    const form = document.getElementById('applicationForm');
    
    if (form) form.reset();
    
    // Alisin ang mga error indicators at warnings
    document.querySelectorAll('.field-error').forEach(error => error.remove());
    document.querySelectorAll('.error').forEach(field => field.classList.remove('error'));
    
    const fileName = document.getElementById('resumeFileName');
    if (fileName) fileName.textContent = '';
    
    // I-reset pabalik sa default state ang mga dropdowns
    document.querySelectorAll('select').forEach(select => {
        select.selectedIndex = 0;
    });
    
    // I-lock at i-clear ang mga dependent address fields
    ['addressProvince', 'addressCity', 'addressBarangay'].forEach(id => {
        const select = document.getElementById(id);
        if (select) {
            select.disabled = true;
            select.innerHTML = `<option value="">Select ${id.replace('address', '')}</option>`;
        }
    });
    
    if (typeof updateFormState === 'function') updateFormState();
    
    // I-reset ang submit button
    const submitBtn = document.getElementById('submitBtn');
    if (submitBtn) {
        submitBtn.disabled = false;
        submitBtn.textContent = 'Submit Application';
    }
    
    const submitStatus = document.getElementById('submitStatus');
    if (submitStatus) submitStatus.textContent = '';
    
    window.scrollTo({ top: 0, behavior: 'smooth' });
}