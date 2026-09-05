<?php
    session_start();
    include('config/connection.php');
    include('config/application_API.php');

?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Application Form</title>
    <link rel="stylesheet" href="css/Applicants.css" />
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
                        <input type="text" name="lastname" placeholder="Enter your last name" class="form-control <?= (!empty($lastname_err)) ? 'is-invalid' : '' ?>" value="<?= htmlspecialchars($_POST['lastname'] ?? $view_data['lastname'] ?? '') ?>" required>
                        <?php if (!empty($lastname_err)): ?><div class="invalid-feedback"><?= htmlspecialchars($lastname_err) ?></div><?php endif; ?>
                    </div>
                    <div class="field">
                        <label for="firstName">First Name</label>
                        <input type="text" name="firstname" placeholder="Enter your first name" class="form-control <?= (!empty($firstname_err)) ? 'is-invalid' : '' ?>" value="<?= htmlspecialchars($_POST['firstname'] ?? $view_data['firstname'] ?? '') ?>" required>
                        <?php if (!empty($firstname_err)): ?><div class="invalid-feedback"><?= htmlspecialchars($firstname_err) ?></div><?php endif; ?>
                    </div>
                    <div class="field">
                        <label for="middleName">Middle Name</label>
                        <input type="text" name="middlename" placeholder="Enter your middle name" class="form-control <?= (!empty($middlename_err)) ? 'is-invalid' : '' ?>" value="<?= htmlspecialchars($_POST['middlename'] ?? $view_data['middlename'] ?? '') ?>">
                        <?php if (!empty($middlename_err)): ?><div class="invalid-feedback"><?= htmlspecialchars($middlename_err) ?></div><?php endif; ?>
                    </div>
                </div>
            </fieldset>

            <!-- CONTACT FIELDS -->
            <!-- DB Columns: phone_number, email_address, facebook_url -->
            <fieldset class="contacts">
                <legend>Contacts</legend>
                <div class="field-grid">
                    <div class="field">
                        <label for="phoneNumber">Contact Number</label>
                        <input type="text" name="phone_number" placeholder="+64" class="form-control <?= (!empty($contact_number_err)) ? 'is-invalid' : '' ?>" value="<?= htmlspecialchars($_POST['contact_number'] ?? $view_data['contact_number'] ?? '') ?>" required>
                        <?php if (!empty($contact_number_err)): ?><div class="invalid-feedback"><?= htmlspecialchars($contact_number_err) ?></div><?php endif; ?>
                    </div>
                    <div class="field">
                        <label for="gmail">Email</label>
                        <input type="email" name="email_address" placeholder="Enter your email address" class="form-control <?= (!empty($email_err)) ? 'is-invalid' : '' ?>" value="<?= htmlspecialchars($_POST['email'] ?? $view_data['email'] ?? '') ?>" required>
                        <?php if (!empty($email_err)): ?><div class="invalid-feedback"><?= htmlspecialchars($email_err) ?></div><?php endif; ?>
                    </div>
                    <div class="field">
                        <label for="facebookUrl">Facebook Link</label>
                        <input type="text" name="facebook" placeholder="https://facebook.com/username" class="form-control <?= (!empty($facebook_err)) ? 'is-invalid' : '' ?>" value="<?= htmlspecialchars($_POST['facebook'] ?? $view_data['facebook'] ?? '') ?>" required>
                        <?php if (!empty($facebook_err)): ?><div class="invalid-feedback"><?= htmlspecialchars($facebook_err) ?></div><?php endif; ?>
                    </div>
                </div>
            </fieldset>

            <!-- ADDRESS -->
            <!-- DB Columns: address_region, address_province, address_city, address_barangay, address_street, address_house_number -->
            <fieldset class="address">
                <legend>Address</legend>
                <div class="field-grid">
                    <div class="field">
                        <div class="form-group">
                            <label for="addressRegion">Region</label>
                            <!-- Gagamitin ng JS para i-populate ang mga rehiyon -->
                            <select id="addressRegion" name="address_region" required>
                                <option value="">Select Region</option>
                            </select>
                        </div>
                    </div>

                    <div class="field">
                        <div class="form-group">
                            <label for="addressProvince">Province</label>
                            <!-- Naka-disabled sa umpisa; mag-aaktibo lang kapag nakapili na ng Region -->
                            <select id="addressProvince" name="address_province" required disabled>
                                <option value="">Select Province</option>
                            </select>
                        </div>
                    </div>
                    <div class="field">
                        <div class="form-group">
                            <label for="addressCity">City / Municipality</label>
                            <!-- Naka-disabled sa umpisa; mag-aaktibo kapag may napiling Province/Region -->
                            <select id="addressCity" name="address_city" required disabled>
                                <option value="">Select City/Municipality</option>
                            </select>
                        </div>
                    </div>
                    <div class="field">
                        <div class="form-group">
                            <label for="addressBarangay">Barangay</label>
                            <!-- Naka-disabled sa umpisa; mag-aaktibo kapag may napiling City -->
                            <select id="addressBarangay" name="address_barangay" required disabled>
                                <option value="">Select Barangay</option>
                            </select>
                        </div>
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
                    <select name="role" class="form-select">
                        <option value="HR" <?= (isset($_POST['role']) && $_POST['role'] == 'HR') ? 'selected' : '' ?>>HR</option>
                        <option value="payroll" <?= (isset($_POST['role']) && $_POST['role'] == 'payroll') ? 'selected' : '' ?>>Payroll</option>
                        <optio3n value="super" <?= (isset($_POST['role']) && $_POST['role'] == 'super') ? 'selected' : '' ?>>Supervisor</optio3n>
                        <option value="log" <?= (isset($_POST['role']) && $_POST['role'] == 'log') ? 'selected' : '' ?>>Logistics</option>
                        <option value="pro" <?= (isset($_POST['role']) && $_POST['role'] == 'pro') ? 'selected' : '' ?>>Production</option>
                    </select>
                </div>
            </fieldset>

            <!-- WORK EXPERIENCE -->
            <!-- DB Columns: work_company, work_position, work_years, work_start_date, work_end_date -->
            <fieldset class="work-experience">
                <legend>Work Experience</legend>
                <div class="field-grid">
                    <div class="field">
                        <label for="workCompany">Company name</label>
                        <input type="text" name="workcompany" placeholder="Enter company name" class="form-control <?= (!empty($company_name_err)) ? 'is-invalid' : '' ?>" value="<?= htmlspecialchars($_POST['company_name'] ?? $view_data['company_name'] ?? '') ?>" required>
                        <?php if (!empty($company_name_err)): ?><div class="invalid-feedback"><?= htmlspecialchars($company_name_err) ?></div><?php endif; ?>
                    </div>
                    <div class="field">
                        <label for="workPosition">Position</label>
                        <input type="text" name="workposition" placeholder="Enter position" class="form-control <?= (!empty($position_err)) ? 'is-invalid' : '' ?>" value="<?= htmlspecialchars($_POST['position'] ?? $view_data['position'] ?? '') ?>" required>
                        <?php if (!empty($position_err)): ?><div class="invalid-feedback"><?= htmlspecialchars($position_err) ?></div><?php endif; ?>
                    </div>
                    <div class="field">
                        <label for="workStartDate">Start date</label>
                        <input type="date" name="workstartdate" placeholder="Enter start date" class="form-control <?= (!empty($date_of_stay_err)) ? 'is-invalid' : '' ?>" value="<?= htmlspecialchars($_POST['date_of_stay'] ?? $view_data['date_of_stay'] ?? '') ?>" required>
                        <?php if (!empty($date_of_stay_err)): ?><div class="invalid-feedback"><?= htmlspecialchars($date_of_stay_err) ?></div><?php endif; ?>
                    </div>
                    <div class="field">
                        <label for="workEndDate">End date</label>
                        <input type="date" name="workenddate" placeholder="Enter end date" class="form-control <?= (!empty($date_of_end_err)) ? 'is-invalid' : '' ?>" value="<?= htmlspecialchars($_POST['date_of_end'] ?? $view_data['date_of_end'] ?? '') ?>" required>
                        <?php if (!empty($date_of_end_err)): ?><div class="invalid-feedback"><?= htmlspecialchars($date_of_end_err) ?></div><?php endif; ?>
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
                    <select name="role" class="form-select">
                        <option value="HR" <?= (isset($_POST['role']) && $_POST['role'] == 'HR') ? 'selected' : '' ?>>HR</option>
                        <option value="payroll" <?= (isset($_POST['role']) && $_POST['role'] == 'payroll') ? 'selected' : '' ?>>Payroll</option>
                        <option value="super" <?= (isset($_POST['role']) && $_POST['role'] == 'super') ? 'selected' : '' ?>>Supervisor</option>
                        <option value="log" <?= (isset($_POST['role']) && $_POST['role'] == 'log') ? 'selected' : '' ?>>Logistics</option>
                        <option value="pro" <?= (isset($_POST['role']) && $_POST['role'] == 'pro') ? 'selected' : '' ?>>Production</option>
                    </select>
                </div> 
            </fieldset>
        </div>
        
        <!-- STEP 4: AVAILABILITY-->
        <div class="card" data-section="4">
            <p class="section-title">Availability</p>
            
            <!-- AVAILABILITY DATE -->
            <!-- DB Column: availability_start_date -->
            <div class="field single-field">
                <label for="availabilityDate">When are you available to start</label>
                <input type="date" name="available" placeholder="Enter date" class="form-control <?= (!empty($start_date_err)) ? 'is-invalid' : '' ?>" value="<?= htmlspecialchars($_POST['start_date'] ?? $view_data['start_date'] ?? '') ?>" required>
                        <?php if (!empty($start_date_err)): ?><div class="invalid-feedback"><?= htmlspecialchars($start_date_err) ?></div><?php endif; ?>
            </div>

            <!-- RESUME UPLOAD -->
            <!-- DB Column: resume_filename -->
            <form action="applicattion_API.php" method="POST" enctype="multipart/form-data">
                <div class="field resume-field">
                    <label for="resumeFile">Resume / CV</label>
                        <div class="resume-upload">
                            <input type="file" id="resumeFile" name="resume" 
                                accept=".pdf,.doc,.docx,.jpg,.jpeg,.png" data-required />
                            <p class="hint">Attach your resume (PDF, Word, or Image, max ~10MB).</p>
                            <p class="file-name" id="resumeFileName"></p>
                        </div>
                </div>
                    <button type="submit">Submit</button>
                </form>
        </div>

        <div class="submit-row">
            <button type="submit" class="submit-btn" id="submitBtn">Submit Application</button>
            <button type="button" class="submit-btn" id="backBtn"><a href="TaskTrackWeb.php">Back</a></button>
            <p class="submit-status" id="submitStatus"></p>
        </div>

    </form>

    <script src="js/Applicants.js"></script>
</body>
</html>