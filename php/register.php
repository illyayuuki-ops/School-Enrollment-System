<?php
declare(strict_types=1);

require_once __DIR__ . '/db_connect.php';

$pdo = db_connect();

session_start([
    'cookie_httponly' => true,
    'cookie_samesite' => 'Lax',
    'cookie_secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
]);

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$submitted = isset($_GET['submitted']) && $_GET['submitted'] === '1';
$application_number = $_SESSION['application_number'] ?? null;
if ($submitted) {
    unset($_SESSION['application_number']);
}

$old = $_SESSION['old_input'] ?? [];
if ($old) {
    unset($_SESSION['old_input']);
}

$error = $_SESSION['register_error'] ?? null;
if ($error) {
    unset($_SESSION['register_error']);
}

$levels = $pdo->query("SELECT id, level_type, name FROM enrollment_levels WHERE active = TRUE ORDER BY level_type, display_order")->fetchAll();
$courses = $pdo->query("SELECT id, level_type, name, major_track FROM enrollment_courses WHERE active = TRUE ORDER BY level_type, display_order")->fetchAll();
$semesters = $pdo->query("SELECT id, name FROM enrollment_semesters ORDER BY display_order")->fetchAll();

$levels_by_type = [];
foreach ($levels as $l) {
    $levels_by_type[$l['level_type']][] = $l;
}
$courses_by_type = [];
foreach ($courses as $c) {
    $courses_by_type[$c['level_type']][] = $c;
}
$today = (new DateTime())->format('F j, Y');
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Pre-Registration · Steiner Maris Academy</title>
    <link rel="icon" type="image/png" href="/assets/logo.png">
    <link rel="stylesheet" href="/assets/app.css">
    <link rel="stylesheet" href="/assets/login.css">
    <link rel="stylesheet" href="/assets/enrollment.css">
</head>
<body class="login-page">
    <div class="portal-frame">
        <aside class="portal-sidebar">
            <a class="portal-brand" href="/login/student.php" aria-label="Steiner Maris Academy SMA Portal">
                <img src="/assets/logo.png" alt="Steiner Maris Academy" class="brand-logo" style="height:40px;width:auto">
                <span class="portal-brand-copy">
                    <strong>Steiner Maris Academy</strong>
                    <small>SMA Portal</small>
                </span>
            </a>

            <div class="portal-welcome">
                <span class="eyebrow">Pre-Registration</span>
                <h1>New Student Application</h1>
                <p>Fill out the form below to submit your pre-registration application.</p>
            </div>

            <div class="portal-sidebar-footer">
                <p>© 2026 Steiner Maris Academy</p>
                <small>Authorized users only</small>
            </div>
        </aside>

        <main class="portal-main">
            <header class="portal-topbar">
                <div class="portal-topbar-welcome">
                    <span class="topbar-menu" aria-hidden="true">☰</span>
                    <span class="eyebrow">SMA Portal</span>
                    <strong>Public Pre-Registration Form</strong>
                </div>
                <div class="portal-topbar-meta">
                    <span class="topbar-year">Today is <?= htmlspecialchars($today) ?></span>
                </div>
            </header>

            <section class="portal-content">
                <div class="portal-content-inner">
                    <div class="portal-intro">
                        <span class="eyebrow">Steiner Maris Academy</span>
                        <h2>Pre-Registration Application</h2>
                        <p>All fields are required. If not applicable, put dash (-).</p>
                    </div>

                    <?php if ($submitted && $application_number): ?>
                        <div class="alert alert-success" role="alert">
                            <strong>Application Submitted!</strong>
                            Your pre-registration has been received. Your application number is:
                            <code><?= htmlspecialchars($application_number) ?></code>
                            Please save this number for future reference.
                        </div>
                    <?php endif; ?>

                    <?php if ($error): ?>
                        <div class="alert alert-error" role="alert"><?= htmlspecialchars($error) ?></div>
                    <?php endif; ?>

                    <form method="post" action="/register_submit.php" novalidate id="enrollment-form">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>">

                        <div class="form-section">
                            <h3>Application Type</h3>
                            <div class="form-row radio-group" data-field="enrollee_type">
                                <label><input type="radio" name="enrollee_type" value="new" <?= ($old['enrollee_type'] ?? 'new') === 'new' ? 'checked' : '' ?>> New</label>
                                <label><input type="radio" name="enrollee_type" value="old" <?= ($old['enrollee_type'] ?? '') === 'old' ? 'checked' : '' ?>> Old</label>
                                <label><input type="radio" name="enrollee_type" value="returnee" <?= ($old['enrollee_type'] ?? '') === 'returnee' ? 'checked' : '' ?>> Returnee</label>
                                <label><input type="radio" name="enrollee_type" value="recontinue" <?= ($old['enrollee_type'] ?? '') === 'recontinue' ? 'checked' : '' ?>> Recontinue</label>
                            </div>
                        </div>

                        <div class="form-section">
                            <h3>Program Selection</h3>
                            <div class="form-grid">
                                <div class="form-field">
                                    <label for="level_type">Level Type *</label>
                                    <select id="level_type" name="level_type" required data-toggle="level-type">
                                        <option value="">-- Select Level Type --</option>
                                        <option value="BED" <?= ($old['level_type'] ?? '') === 'BED' ? 'selected' : '' ?>>BED</option>
                                        <option value="College" <?= ($old['level_type'] ?? '') === 'College' ? 'selected' : '' ?>>College</option>
                                        <option value="TESDA" <?= ($old['level_type'] ?? '') === 'TESDA' ? 'selected' : '' ?>>TESDA</option>
                                    </select>
                                </div>

                                <div class="form-field" data-show-for="BED College">
                                    <label for="level">Level *</label>
                                    <select id="level" name="level" required>
                                        <option value="">-- Select Level --</option>
                                    </select>
                                </div>

                                <div class="form-field" data-show-for="College">
                                    <label for="semester">Semester *</label>
                                    <select id="semester" name="semester">
                                        <option value="">-- Select Semester --</option>
                                        <?php foreach ($semesters as $s): ?>
                                            <option value="<?= htmlspecialchars($s['name']) ?>" <?= ($old['semester'] ?? '') === $s['name'] ? 'selected' : '' ?>><?= htmlspecialchars($s['name']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div class="form-field">
                                    <label for="modality">Modality *</label>
                                    <select id="modality" name="modality" required>
                                        <option value="FACE-TO-FACE" <?= ($old['modality'] ?? 'FACE-TO-FACE') === 'FACE-TO-FACE' ? 'selected' : '' ?>>FACE-TO-FACE</option>
                                        <option value="ONLINE" <?= ($old['modality'] ?? '') === 'ONLINE' ? 'selected' : '' ?>>ONLINE</option>
                                        <option value="HYBRID" <?= ($old['modality'] ?? '') === 'HYBRID' ? 'selected' : '' ?>>HYBRID</option>
                                    </select>
                                </div>
                            </div>

                            <div class="form-grid" id="course-fields">
                                <div class="form-field" data-show-for="College TESDA">
                                    <label for="first_choice_course">First Choice Course *</label>
                                    <select id="first_choice_course" name="first_choice_course">
                                        <option value="">-- Select Course --</option>
                                    </select>
                                </div>
                                <div class="form-field" data-show-for="College TESDA">
                                    <label for="first_major_track">Major/Track</label>
                                    <select id="first_major_track" name="first_major_track">
                                        <option value="">-- Select Major/Track --</option>
                                    </select>
                                </div>
                                <div class="form-field" data-show-for="College TESDA">
                                    <label for="second_choice_course">Second Choice Course</label>
                                    <select id="second_choice_course" name="second_choice_course">
                                        <option value="">-- Select Course --</option>
                                    </select>
                                </div>
                                <div class="form-field" data-show-for="College TESDA">
                                    <label for="second_major_track">Major/Track</label>
                                    <select id="second_major_track" name="second_major_track">
                                        <option value="">-- Select Major/Track --</option>
                                    </select>
                                </div>
                                <div class="form-field" data-show-for="TESDA">
                                    <label for="nc_level">NC Level *</label>
                                    <select id="nc_level" name="nc_level">
                                        <option value="">-- Select NC Level --</option>
                                        <option value="NC I" <?= ($old['nc_level'] ?? '') === 'NC I' ? 'selected' : '' ?>>NC I</option>
                                        <option value="NC II" <?= ($old['nc_level'] ?? '') === 'NC II' ? 'selected' : '' ?>>NC II</option>
                                        <option value="NC III" <?= ($old['nc_level'] ?? '') === 'NC III' ? 'selected' : '' ?>>NC III</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <div class="form-section contact-strip">
                            <h3>Contact Information</h3>
                            <div class="form-grid">
                                <div class="form-field">
                                    <label for="email">Email *</label>
                                    <input type="email" id="email" name="email" required value="<?= htmlspecialchars($old['email'] ?? '') ?>">
                                </div>
                                <div class="form-field">
                                    <label for="contact_number">Contact Number *</label>
                                    <input type="tel" id="contact_number" name="contact_number" value="<?= htmlspecialchars($old['contact_number'] ?? '-') ?>">
                                </div>
                                <div class="form-field" data-show-for="BED">
                                    <label for="lrn">LRN *</label>
                                    <input type="text" id="lrn" name="lrn" value="<?= htmlspecialchars($old['lrn'] ?? '-') ?>">
                                </div>
                            </div>
                        </div>

                        <div class="form-section">
                            <h3>Personal Information</h3>
                            <div class="form-grid">
                                <div class="form-field">
                                    <label for="last_name">Last Name *</label>
                                    <input type="text" id="last_name" name="last_name" required value="<?= htmlspecialchars($old['last_name'] ?? '') ?>">
                                </div>
                                <div class="form-field">
                                    <label for="first_name">First Name *</label>
                                    <input type="text" id="first_name" name="first_name" required value="<?= htmlspecialchars($old['first_name'] ?? '') ?>">
                                </div>
                                <div class="form-field">
                                    <label for="middle_name">Middle Name</label>
                                    <input type="text" id="middle_name" name="middle_name" value="<?= htmlspecialchars($old['middle_name'] ?? '-') ?>">
                                </div>
                                <div class="form-field">
                                    <label for="date_of_birth">Date of Birth</label>
                                    <input type="date" id="date_of_birth" name="date_of_birth" value="<?= htmlspecialchars($old['date_of_birth'] ?? '') ?>">
                                </div>
                                <div class="form-field">
                                    <label for="place_of_birth">Place of Birth</label>
                                    <input type="text" id="place_of_birth" name="place_of_birth" value="<?= htmlspecialchars($old['place_of_birth'] ?? '-') ?>">
                                </div>
                                <div class="form-field">
                                    <label for="gender">Gender</label>
                                    <select id="gender" name="gender">
                                        <option value="">-- Select --</option>
                                        <option value="MALE" <?= ($old['gender'] ?? '') === 'MALE' ? 'selected' : '' ?>>Male</option>
                                        <option value="FEMALE" <?= ($old['gender'] ?? '') === 'FEMALE' ? 'selected' : '' ?>>Female</option>
                                    </select>
                                </div>
                                <div class="form-field">
                                    <label for="nationality">Nationality</label>
                                    <input type="text" id="nationality" name="nationality" value="<?= htmlspecialchars($old['nationality'] ?? 'FILIPINO') ?>">
                                </div>
                                <div class="form-field">
                                    <label for="religion">Religion</label>
                                    <input type="text" id="religion" name="religion" value="<?= htmlspecialchars($old['religion'] ?? '-') ?>">
                                </div>
                            </div>
                        </div>

                        <div class="form-section">
                            <h3>Address</h3>
                            <div class="form-grid">
                                <div class="form-field">
                                    <label for="street_no">St. No.</label>
                                    <input type="text" id="street_no" name="street_no" value="<?= htmlspecialchars($old['street_no'] ?? '-') ?>">
                                </div>
                                <div class="form-field">
                                    <label for="street_name">St. Name</label>
                                    <input type="text" id="street_name" name="street_name" value="<?= htmlspecialchars($old['street_name'] ?? '-') ?>">
                                </div>
                                <div class="form-field">
                                    <label for="subdivision">Subd./Village</label>
                                    <input type="text" id="subdivision" name="subdivision" value="<?= htmlspecialchars($old['subdivision'] ?? '-') ?>">
                                </div>
                                <div class="form-field">
                                    <label for="province">Province</label>
                                    <input type="text" id="province" name="province" value="<?= htmlspecialchars($old['province'] ?? '-') ?>">
                                </div>
                                <div class="form-field">
                                    <label for="town_city">Town/City</label>
                                    <input type="text" id="town_city" name="town_city" value="<?= htmlspecialchars($old['town_city'] ?? '-') ?>">
                                </div>
                                <div class="form-field">
                                    <label for="barangay">Barangay</label>
                                    <input type="text" id="barangay" name="barangay" value="<?= htmlspecialchars($old['barangay'] ?? '-') ?>">
                                </div>
                            </div>
                        </div>

                        <div class="form-section">
                            <h3>Father's Information</h3>
                            <div class="form-grid">
                                <div class="form-field"><label for="father_name">Name</label><input type="text" id="father_name" name="father_name" value="<?= htmlspecialchars($old['father_name'] ?? '-') ?>"></div>
                                <div class="form-field"><label for="father_occupation">Occupation</label><input type="text" id="father_occupation" name="father_occupation" value="<?= htmlspecialchars($old['father_occupation'] ?? '-') ?>"></div>
                                <div class="form-field"><label for="father_mobile">Mobile</label><input type="tel" id="father_mobile" name="father_mobile" value="<?= htmlspecialchars($old['father_mobile'] ?? '-') ?>"></div>
                            </div>
                        </div>

                        <div class="form-section">
                            <h3>Mother's Information</h3>
                            <div class="form-grid">
                                <div class="form-field"><label for="mother_name">Name</label><input type="text" id="mother_name" name="mother_name" value="<?= htmlspecialchars($old['mother_name'] ?? '-') ?>"></div>
                                <div class="form-field"><label for="mother_occupation">Occupation</label><input type="text" id="mother_occupation" name="mother_occupation" value="<?= htmlspecialchars($old['mother_occupation'] ?? '-') ?>"></div>
                                <div class="form-field"><label for="mother_mobile">Mobile</label><input type="tel" id="mother_mobile" name="mother_mobile" value="<?= htmlspecialchars($old['mother_mobile'] ?? '-') ?>"></div>
                            </div>
                        </div>

                        <div class="form-section">
                            <h3>Guardian's Information</h3>
                            <div class="form-grid">
                                <div class="form-field"><label for="guardian_name">Name</label><input type="text" id="guardian_name" name="guardian_name" value="<?= htmlspecialchars($old['guardian_name'] ?? '-') ?>"></div>
                                <div class="form-field"><label for="guardian_occupation">Occupation</label><input type="text" id="guardian_occupation" name="guardian_occupation" value="<?= htmlspecialchars($old['guardian_occupation'] ?? '-') ?>"></div>
                                <div class="form-field"><label for="guardian_mobile">Mobile</label><input type="tel" id="guardian_mobile" name="guardian_mobile" value="<?= htmlspecialchars($old['guardian_mobile'] ?? '-') ?>"></div>
                                <div class="form-field"><label for="guardian_email">Email</label><input type="email" id="guardian_email" name="guardian_email" value="<?= htmlspecialchars($old['guardian_email'] ?? '-') ?>"></div>
                            </div>
                        </div>

                        <div class="form-section">
                            <h3>School Last Attended</h3>
                            <div class="form-grid">
                                <div class="form-field"><label for="school_last_attended">School Name</label><input type="text" id="school_last_attended" name="school_last_attended" value="<?= htmlspecialchars($old['school_last_attended'] ?? '-') ?>"></div>
                                <div class="form-field"><label for="school_address">School Address</label><input type="text" id="school_address" name="school_address" value="<?= htmlspecialchars($old['school_address'] ?? '-') ?>"></div>
                                <div class="form-field"><label for="school_year">School Year</label><input type="text" id="school_year" name="school_year" value="<?= htmlspecialchars($old['school_year'] ?? '-') ?>"></div>
                            </div>
                        </div>

                        <div class="form-section">
                            <h3>Additional Information</h3>
                            <div class="form-grid">
                                <div class="form-field" data-show-for="BED">
                                    <label for="comorbidity">Comorbidity</label>
                                    <input type="text" id="comorbidity" name="comorbidity" value="<?= htmlspecialchars($old['comorbidity'] ?? '-') ?>">
                                </div>
                                <div class="form-field" data-show-for="BED">
                                    <label for="parental_consent">Parental Consent</label>
                                    <select id="parental_consent" name="parental_consent">
                                        <option value="">-- Select --</option>
                                        <option value="YES" <?= ($old['parental_consent'] ?? '') === 'YES' ? 'selected' : '' ?>>Yes</option>
                                        <option value="NO" <?= ($old['parental_consent'] ?? '') === 'NO' ? 'selected' : '' ?>>No</option>
                                    </select>
                                </div>
                                <div class="form-field">
                                    <label for="mode_of_transportation">Mode of Transportation</label>
                                    <input type="text" id="mode_of_transportation" name="mode_of_transportation" value="<?= htmlspecialchars($old['mode_of_transportation'] ?? '-') ?>">
                                </div>
                            </div>
                        </div>

                        <div class="form-section">
                            <h3>Pre-Admission Code</h3>
                            <div class="form-field">
                                <label for="pre_admission_code">Code *</label>
                                <input type="text" id="pre_admission_code" name="pre_admission_code" required value="<?= htmlspecialchars($old['pre_admission_code'] ?? '') ?>">
                                <small class="muted">Enter the pre-admission code provided by the school.</small>
                            </div>
                        </div>

                        <div class="form-actions">
                            <button class="button button-primary" type="submit">Submit Application <span aria-hidden="true">→</span></button>
                        </div>
                    </form>
                </div>
            </section>

            <footer class="portal-footer">
                <span>© 2026 Steiner Maris Academy</span>
                <span>All rights reserved</span>
            </footer>
        </main>
    </div>

    <script>
    (function () {
        var levelsByType = <?= json_encode($levels_by_type) ?>;
        var coursesByType = <?= json_encode($courses_by_type) ?>;

        var levelTypeSel = document.getElementById('level_type');
        var levelSel = document.getElementById('level');
        var courseSel = document.getElementById('first_choice_course');
        var course2Sel = document.getElementById('second_choice_course');
        var majorSel = document.getElementById('first_major_track');
        var major2Sel = document.getElementById('second_major_track');

        function setSelectOptions(select, options, selectedValue) {
            var currentVal = select.value;
            select.innerHTML = '<option value="">-- Select --</option>';
            options.forEach(function(opt) {
                var optEl = document.createElement('option');
                optEl.value = opt.name;
                optEl.textContent = opt.name;
                if (opt.major_track) optEl.dataset.major = opt.major_track;
                select.appendChild(optEl);
            });
            if (selectedValue) select.value = selectedValue;
            else if (currentVal) select.value = currentVal;
        }

        function updateMajors(courseSelect, majorSelect, coursesData) {
            var selectedCourse = courseSelect.value;
            var courseData = coursesData.find(function(c) { return c.name === selectedCourse; });
            majorSelect.innerHTML = '<option value="">-- Select Major/Track --</option>';
            if (courseData && courseData.major_track) {
                var tracks = courseData.major_track.split(',').map(function(t) { return t.trim(); });
                tracks.forEach(function(t) {
                    var opt = document.createElement('option');
                    opt.value = t;
                    opt.textContent = t;
                    majorSelect.appendChild(opt);
                });
            }
        }

        function toggleFields(type) {
            document.querySelectorAll('[data-show-for]').forEach(function(el) {
                var showFor = el.getAttribute('data-show-for').split(' ');
                el.style.display = showFor.includes(type) ? '' : 'none';
                var inputs = el.querySelectorAll('input, select, textarea');
                inputs.forEach(function(input) {
                    input.required = showFor.includes(type) && input.hasAttribute('data-required');
                });
            });
        }

        levelTypeSel.addEventListener('change', function () {
            var type = this.value;
            toggleFields(type);

            var levelOpts = levelsByType[type] || [];
            setSelectOptions(levelSel, levelOpts, '');

            var courseOpts = coursesByType[type] || [];
            setSelectOptions(courseSel, courseOpts, '');
            setSelectOptions(course2Sel, courseOpts, '');

            updateMajors(courseSel, majorSel, courseOpts);
            updateMajors(course2Sel, major2Sel, courseOpts);
        });

        courseSel.addEventListener('change', function () {
            updateMajors(courseSel, majorSel, coursesByType[levelTypeSel.value] || []);
        });
        course2Sel.addEventListener('change', function () {
            updateMajors(course2Sel, major2Sel, coursesByType[levelTypeSel.value] || []);
        });

        document.getElementById('enrollment-form').addEventListener('submit', function(e) {
            var email = document.getElementById('email').value.trim();
            var lastName = document.getElementById('last_name').value.trim();
            var firstName = document.getElementById('first_name').value.trim();
            var levelType = document.getElementById('level_type').value;
            var code = document.getElementById('pre_admission_code').value.trim();

            if (!email || !lastName || !firstName || !levelType || !code) {
                e.preventDefault();
                alert('Please fill in all required fields (marked with *).');
                return false;
            }
            if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
                e.preventDefault();
                alert('Please enter a valid email address.');
                return false;
            }
        });

        if (levelTypeSel.value) {
            toggleFields(levelTypeSel.value);
            var initialType = levelTypeSel.value;
            var levelOpts = levelsByType[initialType] || [];
            setSelectOptions(levelSel, levelOpts, '<?= htmlspecialchars($old['level'] ?? '', ENT_QUOTES) ?>');

            var courseOpts = coursesByType[initialType] || [];
            setSelectOptions(courseSel, courseOpts, '<?= htmlspecialchars($old['first_choice_course'] ?? '', ENT_QUOTES) ?>');
            setSelectOptions(course2Sel, courseOpts, '<?= htmlspecialchars($old['second_choice_course'] ?? '', ENT_QUOTES) ?>');
            updateMajors(courseSel, majorSel, courseOpts);
            updateMajors(course2Sel, major2Sel, courseOpts);
        }
    })();
    </script>
</body>
</html>