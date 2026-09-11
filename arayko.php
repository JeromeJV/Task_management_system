<?php
    include('config/connection.php');

    session_start();
?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Application Form</title>
    <link rel="stylesheet" href="../CSS/Applicants.css" />
</head>
<body>

    <div class="header">
        <div class="steps">

            <div class="step active" data-step="1">
                <div class="connector"><div class="fill"></div></div>
                <div class="circle">1</div>
                <div class="label">Contact Info</div>
            </div>

            <div class="step" data-step="2">
                <div class="connector"><div class="fill"></div></div>
                <div class="circle">2</div>
                <div class="label">Work</div>
            </div>

            <div class="step" data-step="3">
                <div class="connector"><div class="fill"></div></div>
                <div class="circle">3</div>
                <div class="label">Educational Background</div>
            </div>

            <div class="step" data-step="4">
                <div class="connector"><div class="fill"></div></div>
                <div class="circle">4</div>
                <div class="label">Availability</div>
            </div>

        </div>
    </div>

    <form class="application-form" id="applicationForm">

        <!-- STEP 1 : CONTACT INFORMATION                 -->
        <div class="card" data-section="1">
            <p class="section-title">Contact Information</p>

            <!-- NAME FIELDS -->
            <!-- DB Columns: last_name, first_name, middle_name -->
            <fieldset class="name">
                <legend>Name</legend>
                <div class="field-grid">
                    <div class="field">
                        <label for="lastName">Last Name</label>
                        <input type="text" id="lastName" name="last_name" data-required />
                    </div>
                    <div class="field">
                        <label for="firstName">First Name</label>
                        <input type="text" id="firstName" name="first_name" data-required />
                    </div>
                    <div class="field">
                        <label for="middleName">Middle Name</label>
                        <input type="text" id="middleName" name="middle_name" data-required />
                    </div>
                </div>
            </fieldset>

            <!-- CONTACT FIELDS -->
            <!-- DB Columns: phone_number, email_address, facebook_url -->
            <fieldset class="contacts">
                <legend>Contacts</legend>
                <div class="field-grid">
                    <div class="field">
                        <label for="phoneNumber">Phone Number</label>
                        <input type="tel" id="phoneNumber" name="phone_number" 
                               inputmode="numeric" maxlength="11" data-required />
                    </div>
                    <div class="field">
                        <label for="emailAddress">Email</label>
                        <input type="email" id="emailAddress" name="email_address" data-required />
                    </div>
                    <div class="field">
                        <label for="facebookUrl">Facebook Link</label>
                        <input type="url" id="facebookUrl" name="facebook_url" 
                               placeholder="https://facebook.com/username" data-required />
                    </div>
                </div>
            </fieldset>

            <!-- ADDRESS -->
            <!-- DB Columns: address_region, address_province, address_city, address_barangay, address_street, address_house_number -->
            <fieldset class="address">
                <legend>Address</legend>
                <div class="field-grid">
                    <div class="field">
                        <label for="addressRegion">Region</label>
                        <select id="addressRegion" name="address_region" data-required>
                            <option value="">Select Region</option>
                        </select>
                    </div>
                    <div class="field">
                        <label for="addressProvince">Province</label>
                        <select id="addressProvince" name="address_province" data-required disabled>
                            <option value="">Select Province</option>
                        </select>
                    </div>
                    <div class="field">
                        <label for="addressCity">City / Municipality</label>
                        <select id="addressCity" name="address_city" data-required disabled>
                            <option value="">Select City/Municipality</option>
                        </select>
                    </div>
                    <div class="field">
                        <label for="addressBarangay">Barangay</label>
                        <select id="addressBarangay" name="address_barangay" data-required disabled>
                            <option value="">Select Barangay</option>
                        </select>
                    </div>
                    <div class="field">
                        <label for="addressStreet">Street</label>
                        <input type="text" id="addressStreet" name="address_street" data-required />
                    </div>
                    <div class="field">
                        <label for="addressHouseNumber">House Number</label>
                        <input type="text" id="addressHouseNumber" name="address_house_number" data-required />
                    </div>
                </div>
                <div class="api-error" id="apiError">Could not load location data. Please check your internet connection.</div>
            </fieldset>
        </div>

        <!-- STEP 2 : JOB HISTORY                         -->
        <div class="card" data-section="2">
            <p class="section-title">Recent Work</p>

            <!-- POSITION APPLYING FOR -->
            <!-- DB Column: position_applying -->
            <fieldset class="position-information">
                <legend>Position Information</legend>
                <div class="field single-field">
                    <label for="positionApplying">Position</label>
                    <input type="text" id="positionApplying" name="position_applying" data-required />
                </div>
            </fieldset>

            <!-- WORK EXPERIENCE -->
            <!-- DB Columns: work_company, work_position, work_years, work_start_date, work_end_date -->
            <fieldset class="work-experience">
                <legend>Work Experience</legend>
                <div class="field-grid">
                    <div class="field">
                        <label for="workCompany">Company name</label>
                        <input type="text" id="workCompany" name="work_company" data-required />
                    </div>
                    <div class="field">
                        <label for="workPosition">Position</label>
                        <input type="text" id="workPosition" name="work_position" data-required />
                    </div>
                    <div class="field">
                        <label for="workStartDate">Start date</label>
                        <input type="date" id="workStartDate" name="work_start_date" data-required />
                    </div>
                    <div class="field">
                        <label for="workEndDate">End date</label>
                        <input type="date" id="workEndDate" name="work_end_date" data-required />
                    </div>
                </div>
            </fieldset>
        </div>

        <!-- STEP 3 : EDUCATIONAL BACKGROUND              -->
        <div class="card" data-section="3">
            <p class="section-title">Educational Background</p>

            <!-- EDUCATION -->
            <!-- DB Column: education_level -->
            <fieldset class="education">
                <legend>Educational Background</legend>
                    <label for="schoolName">School name</label>
                        <input type="text" id="schoolName" name="school_name" data-required />
                <div class="field single-field">
                    <label for="educationLevel">Highest Education Attained</label>
                    <select id="educationLevel" name="education_level" data-required>
                        <option value="" disabled selected>Select education level</option>
                        <option value="Elementary">Elementary</option>
                        <option value="Junior High School">Junior High School</option>
                        <option value="Senior High School">Senior High School</option>
                        <option value="College/University">College/University</option>
                        <option value="Vocational/Technical">Vocational/Technical</option>
                        <option value="College-Graduate">College Graduate</option>
                    </select>
                </div>
            </fieldset>
        </div>
        
        <!-- STEP 4: AVAILABILITY                        -->
        <div class="card" data-section="4">
            <p class="section-title">Availability</p>
            
            <!-- AVAILABILITY DATE -->
            <!-- DB Column: availability_start_date -->
            <div class="field single-field">
                <label for="availabilityDate">When are you available to start</label>
                <input type="date" id="availabilityDate" name="availability_start_date" data-required />
            </div>

            <!-- RESUME UPLOAD -->
            <!-- DB Column: resume_filename -->
            <div class="field resume-field">
                <label for="resumeFile">Resume / CV</label>
                <div class="resume-upload">
                    <input type="file" id="resumeFile" name="resume_file" 
                           accept=".pdf,.doc,.docx" data-required />
                    <p class="hint">Attach your resume (PDF or Word, max ~5MB).</p>
                    <p class="file-name" id="resumeFileName"></p>
                </div>
            </div>
        </div>

        <div class="submit-row">
            <button type="submit" class="submit-btn" id="submitBtn">Submit Application</button>
            <p class="submit-status" id="submitStatus"></p>
        </div>

    </form>

    <script src="../JS/Applicants.js"></script>
</body>
</html>