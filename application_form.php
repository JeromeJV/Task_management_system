<?php
include 'config/connection.php';
include 'config/application_API.php';
session_start();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Application Form</title>
    <link rel="stylesheet" href="./css/Applicants.css" />
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

    <div class="form-container">
        <form action="" method="post" enctype="multipart/form-data" class="application-form" id="applicationForm">
            
            <?php if (!empty($message)): ?>
                <div class="alert alert-danger" role="alert"><?= htmlspecialchars($message) ?></div>
            <?php endif; ?>

            <!-- STEP 1 : CONTACT INFORMATION -->
            <div class="card" data-section="1">
                <p class="section-title">Contact Information</p>

                <!-- NAME FIELDS -->
                <fieldset class="name">
                    <legend>Name</legend>
                    <div class="field-grid">
                        <div class="field">
                            <label for="lastName">Last Name</label>
                            <input type="text" id="lastName" name="last_name" class="<?= (!empty($lastname_err)) ? 'is-invalid' : '' ?>" value="<?= htmlspecialchars($_POST['last_name'] ?? $_POST['lastname'] ?? '') ?>" required />
                            <?php if (!empty($lastname_err)): ?><div class="invalid-feedback"><?= htmlspecialchars($lastname_err) ?></div><?php endif; ?>
                        </div>
                        <div class="field">
                            <label for="firstName">First Name</label>
                            <input type="text" id="firstName" name="first_name" class="<?= (!empty($firstname_err)) ? 'is-invalid' : '' ?>" value="<?= htmlspecialchars($_POST['first_name'] ?? $_POST['firstname'] ?? '') ?>" required />
                            <?php if (!empty($firstname_err)): ?><div class="invalid-feedback"><?= htmlspecialchars($firstname_err) ?></div><?php endif; ?>
                        </div>
                        <div class="field">
                            <label for="middleName">Middle Name</label>
                            <input type="text" id="middleName" name="middle_name" class="<?= (!empty($middlename_err)) ? 'is-invalid' : '' ?>" value="<?= htmlspecialchars($_POST['middle_name'] ?? $_POST['middlename'] ?? '') ?>" required />
                            <?php if (!empty($middlename_err)): ?><div class="invalid-feedback"><?= htmlspecialchars($middlename_err) ?></div><?php endif; ?>
                        </div>
                    </div>
                </fieldset>

                <!-- CONTACT FIELDS -->
                <fieldset class="contacts">
                    <legend>Contacts</legend>
                    <div class="field-grid">
                        <div class="field">
                            <label for="phoneNumber">Phone Number</label>
                            <input type="tel" id="phoneNumber" name="phone_number" class="<?= (!empty($contact_number_err)) ? 'is-invalid' : '' ?>" value="<?= htmlspecialchars($_POST['phone_number'] ?? $_POST['contact_number'] ?? '') ?>" inputmode="numeric" maxlength="11" placeholder="+63 9xx xxx xxxx" required />
                            <?php if (!empty($contact_number_err)): ?><div class="invalid-feedback"><?= htmlspecialchars($contact_number_err) ?></div><?php endif; ?>
                        </div>
                        <div class="field">
                            <label for="emailAddress">Email</label>
                            <input type="email" id="emailAddress" name="email_address" class="<?= (!empty($email_err)) ? 'is-invalid' : '' ?>" value="<?= htmlspecialchars($_POST['email_address'] ?? $_POST['email'] ?? '') ?>" placeholder="Enter your Email" required />
                            <?php if (!empty($email_err)): ?><div class="invalid-feedback"><?= htmlspecialchars($email_err) ?></div><?php endif; ?>
                        </div>
                        <div class="field">
                            <label for="facebookUrl">Facebook Link</label>
                            <input type="url" id="facebookUrl" name="facebook_url" value="<?= htmlspecialchars($_POST['facebook_url'] ?? '') ?>" placeholder="https://facebook.com/username" required />
                        </div>
                    </div>
                </fieldset>

                <!-- ADDRESS FIELDS -->
                <fieldset class="address">
                    <legend>Address</legend>
                    <div class="field-grid">
                        <div class="field">
                            <label for="addressRegion">Region</label>
                            <select id="addressRegion" name="address_region" required>
                                <option value="">Select Region</option>
                            </select>
                        </div>
                        <div class="field">
                            <label for="addressProvince">Province</label>
                            <select id="addressProvince" name="address_province" required disabled>
                                <option value="">Select Province</option>
                            </select>
                        </div>
                        <div class="field">
                            <label for="addressCity">City / Municipality</label>
                            <select id="addressCity" name="address_city" class="<?= (!empty($city_err)) ? 'is-invalid' : '' ?>" required disabled>
                                <option value="">Select City/Municipality</option>
                            </select>
                            <?php if (!empty($city_err)): ?><div class="invalid-feedback"><?= htmlspecialchars($city_err) ?></div><?php endif; ?>
                        </div>
                        <div class="field">
                            <label for="addressBarangay">Barangay</label>
                            <select id="addressBarangay" name="address_barangay" class="<?= (!empty($barangay_err)) ? 'is-invalid' : '' ?>" required disabled>
                                <option value="">Select Barangay</option>
                            </select>
                            <?php if (!empty($barangay_err)): ?><div class="invalid-feedback"><?= htmlspecialchars($barangay_err) ?></div><?php endif; ?>
                        </div>
                        <div class="field">
                            <label for="addressStreet">Street</label>
                            <input type="text" id="addressStreet" name="address_street" class="<?= (!empty($street_err)) ? 'is-invalid' : '' ?>" value="<?= htmlspecialchars($_POST['address_street'] ?? $_POST['street'] ?? '') ?>" placeholder="Street" required />
                            <?php if (!empty($street_err)): ?><div class="invalid-feedback"><?= htmlspecialchars($street_err) ?></div><?php endif; ?>
                        </div>
                        <div class="field">
                            <label for="addressHouseNumber">House Number</label>
                            <input type="text" id="addressHouseNumber" name="address_house_number" class="<?= (!empty($house_number_err)) ? 'is-invalid' : '' ?>" value="<?= htmlspecialchars($_POST['address_house_number'] ?? $_POST['house_number'] ?? '') ?>" placeholder="House Number" required />
                            <?php if (!empty($house_number_err)): ?><div class="invalid-feedback"><?= htmlspecialchars($house_number_err) ?></div><?php endif; ?>
                        </div>
                    </div>
                    <div class="api-error" id="apiError">Could not load location data. Please check your internet connection.</div>
                </fieldset>
            </div>

            <!-- STEP 2 : JOB HISTORY -->
            <div class="card" data-section="2">
                <p class="section-title">Recent Work</p>

                <fieldset class="position-information">
                    <legend>Position Information</legend>
                    <div class="field single-field">
                        <label for="positionApplying">Position Applied</label>
                        <select id="positionApplying" name="position_applying" class="form-select">
                            <?php $selectedPos = $_POST['position_applying'] ?? $_POST['position_applied'] ?? ''; ?>
                            <option value="HR" <?= ($selectedPos === 'HR') ? 'selected' : '' ?>>HR</option>
                            <option value="Payroll" <?= ($selectedPos === 'Payroll') ? 'selected' : '' ?>>Payroll</option>
                            <option value="Supervisor" <?= ($selectedPos === 'Supervisor') ? 'selected' : '' ?>>Supervisor</option>
                            <option value="Logistics" <?= ($selectedPos === 'Logistics') ? 'selected' : '' ?>>Logistics</option>
                            <option value="Driver" <?= ($selectedPos === 'Driver') ? 'selected' : '' ?>>Driver</option>
                            <option value="Production" <?= ($selectedPos === 'Production') ? 'selected' : '' ?>>Production</option>
                        </select>
                    </div>
                </fieldset>

                <fieldset class="work-experience">
                    <legend>Work Experience</legend>
                    <div class="field-grid">
                        <div class="field">
                            <label for="workCompany">Previous Company Name</label>
                            <input type="text" id="workCompany" name="work_company" class="<?= (!empty($company_name_err)) ? 'is-invalid' : '' ?>" value="<?= htmlspecialchars($_POST['work_company'] ?? $_POST['company_name'] ?? '') ?>" placeholder="Enter Company Name" required />
                            <?php if (!empty($company_name_err)): ?><div class="invalid-feedback"><?= htmlspecialchars($company_name_err) ?></div><?php endif; ?>
                        </div>
                        <div class="field">
                            <label for="workPosition">Previous Position</label>
                            <input type="text" id="workPosition" name="work_position" class="<?= (!empty($position_err)) ? 'is-invalid' : '' ?>" value="<?= htmlspecialchars($_POST['work_position'] ?? $_POST['position'] ?? '') ?>" placeholder="Enter Position" required />
                            <?php if (!empty($position_err)): ?><div class="invalid-feedback"><?= htmlspecialchars($position_err) ?></div><?php endif; ?>
                        </div>
                        <div class="field">
                            <label for="workStartDate">Date Of Start (Previous Work)</label>
                            <input type="date" id="workStartDate" name="work_start_date" value="<?= htmlspecialchars($_POST['work_start_date'] ?? $_POST['date_of_start'] ?? '') ?>" required />
                        </div>
                        <div class="field">
                            <label for="workEndDate">Date Of End</label>
                            <input type="date" id="workEndDate" name="work_end_date" value="<?= htmlspecialchars($_POST['work_end_date'] ?? $_POST['date_of_end'] ?? '') ?>" />
                        </div>
                    </div>
                </fieldset>
            </div>

            <!-- STEP 3 : EDUCATIONAL BACKGROUND -->
            <div class="card" data-section="3">
                <p class="section-title">Educational Background</p>

                <fieldset class="education">
                    <legend>Educational Background</legend>
                    <div class="field mb-3">
                        <label for="schoolName">School Name</label>
                        <input type="text" id="schoolName" name="school_name" value="<?= htmlspecialchars($_POST['school_name'] ?? '') ?>" required />
                    </div>
                    <div class="field single-field">
                        <label for="educationLevel">Highest Education Attained</label>
                        <select id="educationLevel" name="education_level" required>
                            <?php $selectedEdu = $_POST['education_level'] ?? $_POST['education'] ?? ''; ?>
                            <option value="" disabled <?= empty($selectedEdu) ? 'selected' : '' ?>>Select education level</option>
                            <option value="highschool_grad" <?= ($selectedEdu === 'highschool_grad') ? 'selected' : '' ?>>High School Graduate</option>
                            <option value="seniorhigh_under" <?= ($selectedEdu === 'seniorhigh_under') ? 'selected' : '' ?>>Senior High Undergraduate</option>
                            <option value="seniorhigh_grad" <?= ($selectedEdu === 'seniorhigh_grad') ? 'selected' : '' ?>>Senior High Graduate</option>
                            <option value="college_under" <?= ($selectedEdu === 'college_under') ? 'selected' : '' ?>>College Undergraduate</option>
                            <option value="college_grad" <?= ($selectedEdu === 'college_grad') ? 'selected' : '' ?>>College Graduate</option>
                            <option value="vocational" <?= ($selectedEdu === 'vocational') ? 'selected' : '' ?>>Vocational</option>
                            <option value="n/a" <?= ($selectedEdu === 'n/a') ? 'selected' : '' ?>>Not Applicable</option>
                        </select>
                    </div>
                </fieldset>
            </div>

            <!-- STEP 4 : AVAILABILITY -->
            <div class="card" data-section="4">
                <p class="section-title">Availability</p>

                <div class="field single-field mb-3">
                    <label for="availabilityDate">When are you available to start</label>
                    <input type="date" id="availabilityDate" name="availability_start_date" class="<?= (!empty($start_date_err)) ? 'is-invalid' : '' ?>" value="<?= htmlspecialchars($_POST['availability_start_date'] ?? $_POST['start_date'] ?? '') ?>" required />
                    <?php if (!empty($start_date_err)): ?><div class="invalid-feedback"><?= htmlspecialchars($start_date_err) ?></div><?php endif; ?>
                </div>

                <div class="field resume-field mb-3">
                    <label for="resumeFile">Resume / CV</label>
                    <div class="resume-upload">
                        <input type="file" id="resumeFile" name="resume_file" class="<?= (!empty($resume_err)) ? 'is-invalid' : '' ?>" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png" required />
                        <p class="hint small text-muted">Attach your resume (PDF, Word, or Image, max ~10MB).</p>
                        <p class="file-name" id="resumeFileName"></p>
                        <?php if (!empty($resume_err)): ?><div class="invalid-feedback"><?= htmlspecialchars($resume_err) ?></div><?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="submit-row">
                <button type="submit" class="submit-btn btn_font" id="submitBtn" name="submit">Submit Application</button>
                <p class="submit-status" id="submitStatus"></p>
            </div>

        </form>
    </div>

    <script src="./js/applicants.js"></script>
</body>
</html>