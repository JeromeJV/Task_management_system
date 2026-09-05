<?php
    include('config/connection.php');

    session_start();
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>TaskTrack — Streamline Tasks, Empower Your Team</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link
    href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@500;600;700&family=Inter:wght@400;500;600;700&family=IBM+Plex+Mono:wght@500;600&display=swap"
    rel="stylesheet">
    <link rel="stylesheet" href="css/TaskTrackWeb.css">
</head>

<body>
  <!-- NAVIGATION -->
  <nav class="navbar" id="navbar" aria-label="Primary navigation">
    <div class="nav-logo">
      <span class="mark" aria-hidden="true"></span>
      Kairos
    </div>
    <div class="nav-links">
      <button class="nav-link active" type="button" data-target="page-home">Home</button>
      <button class="nav-link" type="button" data-target="page-about">About</button>
      <button class="nav-link" type="button" data-target="page-contact">Contact</button>
      <button class="nav-link" type="button"><a href="index.php">Go to Login</a></button>
      <a class="nav-apply" href="#page-apply" data-target="page-apply">Apply</a>
    </div>

    <button class="nav-toggle" id="navToggle" type="button" aria-label="Open menu" aria-expanded="false">
      <span aria-hidden="true"></span>
    </button>
  </nav>

  <div class="mobile-menu" id="mobileMenu">
    <button class="nav-mobile-link" type="button" data-target="page-home">Home</button>
    <button class="nav-mobile-link" type="button" data-target="page-about">About</button>
    <button class="nav-mobile-link" type="button" data-target="page-contact">Contact</button>
    <button class="nav-mobile-link" type="button" data-target="page-apply">Apply</button>
  </div>

<!-- MEMBER DETAIL -->
<div class="member-detail-overlay" id="memberOverlay"></div>

<aside
  class="member-detail-panel"
  id="memberPanel"
  aria-label="Member details"
>

  <button
    class="member-detail-close"b 
    id="memberClose"
    type="button"
    aria-label="Close member details"
  >
    ✕
  </button>

  <div
    class="member-detail-avatar"
    id="memberDetailAvatar"
  >
    B
  </div>

  <h2
    class="member-detail-name"
    id="memberDetailName">
    Bernard Joseph Sagon
  </h2>

  <div
    class="member-detail-role"
    id="memberDetailRole">
    FRONT-END
  </div>

  <div
    class="member-detail-image"
    id="memberDetailImage">
    </div>

  <div class="member-profile">

    <div class="member-profile-section">
      <h4>Skills</h4>
      <div
        class="member-tags"
        id="memberDetailSkills"
      ></div>
    </div>

    <div class="member-profile-section">
      <h4>Knowledge</h4>
      <div
        class="member-tags"
        id="memberDetailKnowledge"
      ></div>
    </div>

    <div class="member-profile-section">
      <h4>Technologies</h4>
      <div
        class="member-tags"
        id="memberDetailTechnologies"
      ></div>
    </div>

    <div class="member-profile-section">
      <h4>Contribution to TaskTrack</h4>
      <p id="memberDetailContribution"></p>
    </div>
  </div>

  <div class="member-detail-quote">
    <span class="quote-label">
      Favorite Quote
    </span>
    <p id="memberDetailQuote"></p>
  </div>
  
</aside>

  <!-- MAIN CONTENT -->
  <main>
    <!-- HOME -->
    <section id="page-home" class="page">
      <div class="home-inner">
        <div class="home-copy">
          <h1>
            Work smarter.<br>
            Stay <span>on track.</span>
          </h1>

          <p class="home-lede">
            TaskTrack brings tasks, priorities, progress, and team responsibilities
            into one organized workspace, giving teams a clearer way to manage work
            from assignment to completion.
          </p>

          <div class="home-actions">
            <a href="#page-about" class="home-primary" data-target="page-about">
              Explore TaskTrack
            </a>
            <a href="#page-contact" class="home-secondary" data-target="page-contact">
              Contact the team
            </a>
          </div>
        </div>
      </div>
    </section>

    <!-- ABOUT -->
    <section id="page-about" class="page">
      <div class="about-content">
        <div class="about-layout">
          <div class="about-left">
            <div class="mission-card">
              <span class="eyebrow">Simplify work. Maximize productivity.</span>

              <h3>What is TaskTrack</h3>

              <p>
                Kairos is a 3rd party company develop TaskTrack that solves
                a common problem: scattered tasks, unclear priorities, and limited
                progress visibility. The system connects people
                and processes so work stays on track from assignment
                to completion.
              </p>
            </div>

            <div class="mission-points">
              <div class="mission-point">
                <div class="mission-icon" aria-hidden="true">✓</div>
                <div>
                  <strong>Better Task Management</strong>
                  <p>
                    TaskTrack makes it easier to assign, organize, and monitor tasks,
                    ensuring that every employee knows what needs to be done and when.
                  </p>
                </div>
              </div>

              <div class="mission-point">
                <div class="mission-icon" aria-hidden="true">✓</div>
                <div>
                  <strong>Faster Workflow</strong>
                  <p>
                    By streamlining task assignments and progress tracking, TaskTrack reduces delays
                    and unnecessary back-and-forth.
                  </p>
                </div>
              </div>

              <div class="mission-point">
                <div class="mission-icon" aria-hidden="true">✓</div>
                <div>
                  <strong>Real-Time Progress Monitoring</strong>
                  <p>
                    Managers can easily see the status of tasks, track employee progress,
                  </p>
                </div>
              </div>

              <div class="mission-point">
                <div class="mission-icon" aria-hidden="true">✓</div>
                <div>
                  <strong>Improved Team Collaboration</strong>
                  <p>
                    TaskTrack keeps tasks, updates, and responsibilities organized
                    in one system.
                  </p>
                </div>
              </div>
            </div>
          </div>

          <div class="about-right">
            <div class="members-header">
              <h3>Meet the Founders</h3>
            </div>

            <div class="about-features">
              <article class="feature-card" data-member="harvey">
                <div class="member-top">
                  <div class="member-avatar">H</div>
                  <span class="mem-tag">LEADER · BACK-END</span>
                </div>
                <h3>Harvey Jerome Valdepena</h3>
                <p>Back-end development and database architecture.</p>
              </article>

              <article class="feature-card" data-member="bernard">
                <div class="member-top">
                  <div class="member-avatar">B</div>
                  <span class="mem-tag">FRONT-END</span>
                </div>
                <h3>Bernard Joseph Sagon</h3>
                <p>Main UI developer, prototype development, and front-end development.</p>
              </article>

              <article class="feature-card" data-member="joshua">
                <div class="member-top">
                  <div class="member-avatar">J</div>
                  <span class="mem-tag">FRONT-END</span>
                </div>
                <h3>Joshua Miguel Garcia</h3>
                <p>Front-end development and interface implementation.</p>
              </article>

              <article class="feature-card" data-member="irish">
                <div class="member-top">
                  <div class="member-avatar">I</div>
                  <span class="mem-tag">DEVELOPMENT</span>
                </div>
                <h3>Irish Marie Rala</h3>
                <p>UI design and interface development.</p>
              </article>

              <article class="feature-card" data-member="dominic">
                <div class="member-top">
                  <div class="member-avatar">D</div>
                  <span class="mem-tag">DATABASE</span>
                </div>
                <h3>Dominic Dulatre</h3>
                <p>Database development and data management.</p>
              </article>

              <article class="feature-card" data-member="jasmin">
                <div class="member-top">
                  <div class="member-avatar">J</div>
                  <span class="mem-tag">UI / DESIGN</span>
                </div>
                <h3>Jasmin Santos</h3>
                <p>UI design and interface development.</p>
              </article>
            </div>
          </div>
        </div>
      </div>
    </section>

    <!-- CONTACT -->
    <section id="page-contact" class="page">
      <div class="contact-content">
        <h2 class="contact-title">Contact us</h2>
        <p class="contact-invitation">
          Have a question?, reach out one of our developer here
        </p>

        <div class="contact-grid">
          <div class="contact-panel">
            <div class="contact-links">
              <div class="contact-item">
                <a href="https://mail.google.com/mail/u/0/#inbox?compose=new">
                  <span class="contact-icon" aria-hidden="true">✉</span>
                  Email
                </a>
              </div>

              <div class="contact-item">
                <a
                  href="https://www.facebook.com/Bernard.sagon.0918"
                  target="_blank"
                  rel="noopener noreferrer"
                >
                  <span class="contact-icon" aria-hidden="true">f</span>
                  Facebook
                </a>
              </div>

              <div class="contact-item">
                <a
                  href="https://www.instagram.com/aazil.95/"
                  target="_blank"
                  rel="noopener noreferrer"
                >
                  <span class="contact-icon" aria-hidden="true">◎</span>
                  Instagram
                </a>
              </div>
            </div>
          </div>

          <div class="contact-map">
            <h2>Find us</h2>
            <p>215 Valdez Street, Catmon, Malabon City, Philippines</p>

            <iframe
              src="https://www.google.com/maps?q=215+Valdez+St,+Catmon,+Manila,+Metro+Manila&output=embed"
              allowfullscreen
              loading="lazy"
              referrerpolicy="no-referrer-when-downgrade"
              title="Google Maps location of 215 Valdez St, Catmon, Manila, Metro Manila"
            ></iframe>
          </div>
        </div>
      </div>
    </section>

    <!-- APPLY -->
    <section id="page-apply" class="page">
      <div class="apply-inner">
        <h2>As of now our company model is Ginga</h2>

        <p class="apply-lede">
          Ginga is a health-focused beverage company specializing in refreshing powdered drinks made from natural ginger.
        </p>

        <div class="apply-reasons">
          <article class="apply-reason">
            <h3>Healthy &amp; Refreshing</h3>
            <p>
              Ginga provides a refreshing powdered drink made from ginger, offering a flavorful choice for health-conscious consumers.
            </p>
          </article>

          <article class="apply-reason">
            <h3>Made from Ginger</h3>
            <p>
              Our products highlight the natural flavor and benefits of ginger in a convenient and easy-to-prepare drink.
            </p>
          </article>

          <article class="apply-reason">
            <h3>Convenient &amp; Accessible</h3>
            <p>
              Ginga makes enjoying a ginger-based drink simple and convenient, whether at home, at work, or on the go.
            </p>
          </article>
        </div>

        <a href="application_form.php" class="btn-apply-lg">Apply Now</a>
      </div>
    </section>
  </main>

  <footer>
    <span>© 2026 Kairos. All rights reserved.</span>
    <span>Partnered with Ginga</span>
  </footer>
  <script src="js/TaskTrackWeb.js"></script>
</body>
</html>