    <?php
    include 'config/connection.php';
    include 'config/application_API.php';
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Application Form</title>
    </head>
    <body>
        <h2>Application Form</h2>
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
        <div class="form">
            <form action="" method="post" enctype="multipart/form-data" class="p-4 border rounded bg-light style-form-container">
                
                <?php if (!empty($message)): ?>
                    <div class="alert alert-danger" role="alert"><?= $message ?></div>
                <?php endif; ?>

                <!-- Personal Information -->
                <div class="form-group mb-3">
                    <label class="form-label">Last Name:</label>
                    <input type="text" name="lastname" placeholder="Enter your Last Name" class="form-control <?= (!empty($lastname_err)) ? 'is-invalid' : '' ?>" value="<?= htmlspecialchars($_POST['lastname'] ?? '') ?>" required>
                    <?php if (!empty($lastname_err)): ?><div class="invalid-feedback"><?= $lastname_err ?></div><?php endif; ?>
                </div>

                <div class="form-group mb-3">
                    <label class="form-label">First Name:</label>
                    <input type="text" name="firstname" placeholder="Enter your First Name" class="form-control <?= (!empty($firstname_err)) ? 'is-invalid' : '' ?>" value="<?= htmlspecialchars($_POST['firstname'] ?? '') ?>" required>
                    <?php if (!empty($firstname_err)): ?><div class="invalid-feedback"><?= $firstname_err ?></div><?php endif; ?>
                </div>

                <div class="form-group mb-3">
                    <label class="form-label">Middle Name:</label>
                    <input type="text" name="middlename" placeholder="Enter your Middle Name" class="form-control <?= (!empty($middlename_err)) ? 'is-invalid' : '' ?>" value="<?= htmlspecialchars($_POST['middlename'] ?? '') ?>" required>
                    <?php if (!empty($middlename_err)): ?><div class="invalid-feedback"><?= $middlename_err ?></div><?php endif; ?>
                </div>

                <div class="form-group mb-3">
                    <label class="form-label">Contact Number:</label>
                    <input type="text" name="contact_number" placeholder="+63 9xx xxx xxxx" class="form-control <?= (!empty($contact_number_err)) ? 'is-invalid' : '' ?>" value="<?= htmlspecialchars($_POST['contact_number'] ?? '') ?>" required>
                    <?php if (!empty($contact_number_err)): ?><div class="invalid-feedback"><?= $contact_number_err ?></div><?php endif; ?>
                </div>

                <div class="form-group mb-3">
                    <label class="form-label">Email Address</label>
                    <input type="email" name="email" placeholder="Enter your Email" class="form-control <?= (!empty($email_err)) ? 'is-invalid' : '' ?>" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required>
                    <?php if (!empty($email_err)): ?><div class="invalid-feedback"><?= $email_err ?></div><?php endif; ?>
                </div>

                <!-- Address Details -->
                <div class="form-group mb-3">
                    <label class="form-label">House Number</label>
                    <input type="text" name="house_number" placeholder="House Number" class="form-control <?= (!empty($house_number_err)) ? 'is-invalid' : '' ?>" value="<?= htmlspecialchars($_POST['house_number'] ?? '') ?>" required>
                    <?php if (!empty($house_number_err)): ?><div class="invalid-feedback"><?= $house_number_err ?></div><?php endif; ?>
                </div>

                <div class="form-group mb-3">
                    <label class="form-label">Street</label>
                    <input type="text" name="street" placeholder="Street" class="form-control <?= (!empty($street_err)) ? 'is-invalid' : '' ?>" value="<?= htmlspecialchars($_POST['street'] ?? '') ?>" required>
                    <?php if (!empty($street_err)): ?><div class="invalid-feedback"><?= $street_err ?></div><?php endif; ?>
                </div>

                <div class="form-group mb-3">
                    <label class="form-label">Barangay</label>
                    <input type="text" name="barangay" placeholder="Barangay" class="form-control <?= (!empty($barangay_err)) ? 'is-invalid' : '' ?>" value="<?= htmlspecialchars($_POST['barangay'] ?? '') ?>" required>
                    <?php if (!empty($barangay_err)): ?><div class="invalid-feedback"><?= $barangay_err ?></div><?php endif; ?>
                </div>

                <div class="form-group mb-3">
                    <label class="form-label">City</label>
                    <input type="text" name="city" placeholder="City" class="form-control <?= (!empty($city_err)) ? 'is-invalid' : '' ?>" value="<?= htmlspecialchars($_POST['city'] ?? '') ?>" required>
                    <?php if (!empty($city_err)): ?><div class="invalid-feedback"><?= $city_err ?></div><?php endif; ?>
                </div>

                <div class="form-group mb-3">
                    <label class="form-label">Province</label>
                    <input type="text" name="province" placeholder="Province" class="form-control" value="<?= htmlspecialchars($_POST['province'] ?? '') ?>">
                </div>


                <!-- Job Application Details -->
                <div class="form-group mb-3">
                    <label class="form-label">Position Applied</label>
                    <select name="position_applied" class="form-select">
                        <option value="HR" <?= (($_POST['position_applied'] ?? '') === 'HR') ? 'selected' : '' ?>>HR</option>
                        <option value="Payroll" <?= (($_POST['position_applied'] ?? '') === 'Payroll') ? 'selected' : '' ?>>Payroll</option>
                        <option value="Supervisor" <?= (($_POST['position_applied'] ?? '') === 'Supervisor') ? 'selected' : '' ?>>Supervisor</option>
                        <option value="Logistics" <?= (($_POST['position_applied'] ?? '') === 'Logistics') ? 'selected' : '' ?>>Logistics</option>
                        <option value="Driver" <?= (($_POST['position_applied'] ?? '') === 'Driver') ? 'selected' : '' ?>>Driver</option>
                        <option value="Production" <?= (($_POST['position_applied'] ?? '') === 'Production') ? 'selected' : '' ?>>Production</option>
                    </select>
                </div>

                <div class="form-group mb-3">
                    <label class="form-label">Previous Company Name</label>
                    <input type="text" name="company_name" placeholder="Enter Company Name" class="form-control <?= (!empty($company_name_err)) ? 'is-invalid' : '' ?>" value="<?= htmlspecialchars($_POST['company_name'] ?? '') ?>" required>
                    <?php if (!empty($company_name_err)): ?><div class="invalid-feedback"><?= $company_name_err ?></div><?php endif; ?>
                </div>

                <div class="form-group mb-3">
                    <label class="form-label">Previous Position</label>
                    <input type="text" name="position" placeholder="Enter Position" class="form-control <?= (!empty($position_err)) ? 'is-invalid' : '' ?>" value="<?= htmlspecialchars($_POST['position'] ?? '') ?>" required>
                    <?php if (!empty($position_err)): ?><div class="invalid-feedback"><?= $position_err ?></div><?php endif; ?>
                </div>

                <div class="form-group mb-3">
                    <label class="form-label">Date Of Start (Previous Work)</label>
                    <input type="date" name="date_of_start" class="form-control" value="<?= htmlspecialchars($_POST['date_of_start'] ?? '') ?>" required>
                </div>

                <div class="form-group mb-3">
                    <label class="form-label">Date Of End</label>
                    <input type="date" name="date_of_end" class="form-control" value="<?= htmlspecialchars($_POST['date_of_end'] ?? '') ?>">
                </div>

                <div class="form-group mb-3">
                    <label class="form-label">Highest Education</label>
                    <select name="education" class="form-select">
                        <option value="highschool_grad" <?= (($_POST['education'] ?? '') === 'highschool_grad') ? 'selected' : '' ?>>High School Graduate</option>
                        <option value="seniorhigh_under" <?= (($_POST['education'] ?? '') === 'senior_high_undergraduate') ? 'selected' : '' ?>>Senior High Undergraduate</option>
                        <option value="seniorhigh_grad" <?= (($_POST['education'] ?? '') === 'senior_high_graduate') ? 'selected' : '' ?>>Senior High Graduate</option>
                        <option value="college_under" <?= (($_POST['education'] ?? '') === 'college_undergraduate') ? 'selected' : '' ?>>College Undergraduate</option>
                        <option value="college_grad" <?= (($_POST['education'] ?? '') === 'college_graduate') ? 'selected' : '' ?>>College Graduate</option>
                        <option value="vocational" <?= (($_POST['education'] ?? '') === 'vocational') ? 'selected' : '' ?>>Vocational</option>
                        <option value="n/a" <?= (($_POST['education'] ?? '') === 'n/a') ? 'selected' : '' ?>>Not Applicable</option>
                    </select>
                </div>

                <div class="form-group mb-3">
                    <label class="form-label">Start Date</label>
                    <input type="date" name="start_date" class="form-control <?= (!empty($start_date_err)) ? 'is-invalid' : '' ?>" value="<?= htmlspecialchars($_POST['start_date'] ?? '') ?>" required>
                    <?php if (!empty($start_date_err)): ?><div class="invalid-feedback"><?= $start_date_err ?></div><?php endif; ?>
                </div>

                <div class="field resume-field mb-3">
                    <label for="resumeFile" class="form-label">Resume / CV</label>
                    <div class="resume-upload">
                        <input type="file" id="resumeFile" name="resume" class="form-control <?= (!empty($resume_err)) ? 'is-invalid' : '' ?>" accept=".pdf,.jpg,.jpeg,.png" required />
                        <p class="hint small text-muted">Attach your resume (PDF or Image, max ~10MB).</p>
                        <?php if (!empty($resume_err)): ?><div class="invalid-feedback"><?= $resume_err ?></div><?php endif; ?>
                    </div>
                </div>

                <button type="submit" class="btn btn-primary w-100 mb-3 btn_font" name="submit">Submit Application</button>
            </form>
        </div>
            
    

</body>
</html>