/* =========================================================
   NAVIGATION
   ========================================================= */

const navbar = document.getElementById('navbar');
const navToggle = document.getElementById('navToggle');
const mobileMenu = document.getElementById('mobileMenu');

const navLinks =
  document.querySelectorAll('.nav-link');

const navigationTargets =
  document.querySelectorAll('[data-target]');

const sections =
  document.querySelectorAll(
    '#page-home, #page-about, #page-contact, #page-apply'
  );


/* =========================================================
   MOBILE MENU
   ========================================================= */

function closeMobileMenu() {

  mobileMenu.classList.remove('open');

  navToggle.setAttribute(
    'aria-expanded',
    'false'
  );
}


/* =========================================================
   PAGE NAVIGATION
   ========================================================= */

function scrollToPage(sectionId) {

  const section =
    document.getElementById(sectionId);

  if (!section) {
    return;
  }

  section.scrollIntoView({
    behavior: 'smooth',
    block: 'start'
  });

  closeMobileMenu();
}


navigationTargets.forEach((target) => {

  target.addEventListener('click', (event) => {

    const sectionId =
      target.dataset.target;

    if (!sectionId) {
      return;
    }

    event.preventDefault();

    scrollToPage(sectionId);
  });

});


/* =========================================================
   NAVBAR SCROLL
   ========================================================= */

window.addEventListener('scroll', () => {

  navbar.classList.toggle(
    'scrolled',
    window.scrollY > 30
  );

});


/* =========================================================
   MOBILE NAVIGATION TOGGLE
   ========================================================= */

navToggle.addEventListener('click', (event) => {

  event.stopPropagation();

  const isOpen =
    mobileMenu.classList.toggle('open');

  navToggle.setAttribute(
    'aria-expanded',
    String(isOpen)
  );

});


/* =========================================================
   CLOSE MOBILE MENU WHEN CLICKING OUTSIDE
   ========================================================= */

document.addEventListener('click', (event) => {

  if (
    !mobileMenu.contains(event.target) &&
    !navToggle.contains(event.target)
  ) {
    closeMobileMenu();
  }

});


/* =========================================================
   ACTIVE NAVIGATION SECTION
   ========================================================= */

const sectionObserver =
  new IntersectionObserver(
    (entries) => {

      entries.forEach((entry) => {

        if (!entry.isIntersecting) {
          return;
        }

        navLinks.forEach((link) => {

          link.classList.toggle(
            'active',
            link.dataset.target === entry.target.id
          );

        });

      });

    },
    {
      threshold: 0.5
    }
  );


sections.forEach((section) => {

  sectionObserver.observe(section);

});


/* =========================================================
   MEMBER DETAIL ELEMENTS
   ========================================================= */

const memberOverlay =
  document.getElementById('memberOverlay');

const memberPanel =
  document.getElementById('memberPanel');

const memberClose =
  document.getElementById('memberClose');

const memberDetailAvatar =
  document.getElementById('memberDetailAvatar');

const memberDetailName =
  document.getElementById('memberDetailName');

const memberDetailRole =
  document.getElementById('memberDetailRole');

const memberDetailImage =
  document.getElementById('memberDetailImage');

const memberDetailSkills =
  document.getElementById('memberDetailSkills');

const memberDetailKnowledge =
  document.getElementById('memberDetailKnowledge');

const memberDetailTechnologies =
  document.getElementById('memberDetailTechnologies');

const memberDetailContribution =
  document.getElementById(
    'memberDetailContribution'
  );

const memberDetailQuote =
  document.getElementById(
    'memberDetailQuote'
  );

const memberCards =
  document.querySelectorAll('.feature-card');


/* =========================================================
   MEMBER DATA
   ========================================================= */

const memberData = {

  harvey: {

    avatar: 'H',

    name: 'Harvey Jerome Valdepena',

    role: 'LEADER · BACK-END',

    skills: [
      'Planning',
      'Multitasking',
      'Problem Solving',
      'Critical Thinking',
      'Time Management',
      'Leadership'
    ],

    knowledge: [
      'System Architecture',
      'Database Design',
      'Database Management',
      'Web Development',
      'Front-end & Back-end Development',
      'Authentication & Security',
      'Data Management & Integrity',
      'UI/UX & Graphic Design',
      'Object-Oriented Programming',
      'Data Structures'
    ],

    technologies: [
      'PHP',
      'MySQL',
      'SQL Server',
      'Figma',
      'Git',
      'GitHub'
    ],

    contribution:
      'Leads the development team and oversees the back-end infrastructure of TaskTrack. Responsible for ensuring that the system components work together efficiently and reliably.',

    image:
      '../images/harvey.jpg',

    quote:
      'Great things are never done by one person. They are done by a team of people.'
  },


  bernard: {

    avatar: 'B',

    name: 'Bernard Joseph Sagon',

    role: 'FRONT-END',

    skills: [
      'Front-end Development',
      'UI Implementation',
      'Responsive Design',
      'Problem Solving',
      'JavaScript Development'
    ],

    knowledge: [
      'Web Development',
      'User Interface Design',
      'UI/UX Design',
      'Responsive Design',
      'DOM Manipulation',
      'Prototyping'
    ],

    technologies: [
      'HTML',
      'CSS',
      'JavaScript',
      'Figma',
      'Git',
      'GitHub'
    ],

    contribution:
      'Develops the main user interface of TaskTrack and transforms prototypes and system requirements into functional and interactive web pages.',

    image:
      '../images/bernard.jpg',

    quote:
      'Even though I walk through the valley of the shadow of death, I will fear no evil, for you are with me.'
  },


  joshua: {

    avatar: 'J',

    name: 'Joshua Miguel Garcia',

    role: 'FRONT-END',

    skills: [
      'Front-end Development',
      'Responsive Design',
      'Problem Solving',
      'Interface Development',
      'JavaScript Development'
    ],

    knowledge: [
      'Web Development',
      'HTML & CSS',
      'JavaScript',
      'DOM Manipulation',
      'Responsive Design',
      'UI Implementation'
    ],

    technologies: [
      'HTML',
      'CSS',
      'JavaScript',
      'Git',
      'GitHub'
    ],

    contribution:
      'Works on the implementation and refinement of TaskTrack interfaces, helping ensure that the system is consistent, responsive, and easy to navigate.',

    image:
      '../images/joshua.jpg',

    quote:
      'Every line of code is a step closer to making an idea real.'
  },


  dominic: {

    avatar: 'D',

    name: 'Dominic Dulatre',

    role: 'DATABASE',

    skills: [
      'Database Management',
      'Problem Solving',
      'Data Organization',
      'Query Development',
      'Critical Thinking'
    ],

    knowledge: [
      'Database Design',
      'Data Modeling',
      'SQL & Relational Databases',
      'Database Security',
      'Data Integrity',
      'Normalization',
      'CRUD Operations'
    ],

    technologies: [
      'MySQL',
      'SQL Server',
      'phpMyAdmin',
      'Git',
      'GitHub'
    ],

    contribution:
      'Designs and manages the database structure used by TaskTrack to organize users, tasks, assignments, progress records, and other system information.',

    image:
      '../images/dominic.jpg',

    quote:
      'For the righteous falls seven times and rises again, but the wicked stumble in times of calamity.'
  },


  irish: {

    avatar: 'I',

    name: 'Irish Marie Rala',

    role: 'UI / DESIGN',

    skills: [
      'Problem Solving',
      'Time Management',
      'Adaptability',
      'Responsibility',
      'Perseverance'
    ],

    knowledge: [
      'UI/UX Design',
      'Graphic Design',
      'System Architecture'
    ],

    technologies: [
      'Figma',
      'GitHub'
    ],

    contribution:
      'Contributes to the visual design and interface development of TaskTrack, focusing on usability, layout organization, and maintaining a consistent design across the platform.',

    image:
      '../images/irish.jpg',

    quote:
      'Design is about making things simple, useful, and meaningful.'
  },


  jasmin: {

    avatar: 'J',

    name: 'Jasmin Santos',

    role: 'UI / DESIGN',

    skills: [
      'UI Design',
      'Creative Thinking',
      'Visual Design',
      'Layout Design',
      'Attention to Detail'
    ],

    knowledge: [
      'UI/UX Design',
      'Visual Hierarchy',
      'Typography',
      'Color Theory',
      'Design Principles',
      'Prototyping'
    ],

    technologies: [
      'Figma',
      'HTML',
      'CSS',
      'GitHub'
    ],

    contribution:
      'Helps develop and refine the visual identity of TaskTrack while focusing on intuitive layouts, consistent styling, and a user-friendly interface.',

    image:
      '../images/jasmin.jpg',

    quote:
      'Creativity turns simple ideas into something people remember.'
  }

};


/* =========================================================
   CREATE MEMBER TAGS
   ========================================================= */

function createMemberTags(
  container,
  items
) {

  container.replaceChildren();

  items.forEach((item) => {

    const tag =
      document.createElement('span');

    tag.className = 'member-tag';

    tag.textContent = item;

    container.appendChild(tag);

  });

}


/* =========================================================
   OPEN MEMBER DETAIL
   ========================================================= */

function openMemberDetail(memberKey) {

  const member =
    memberData[memberKey];

  if (!member) {

    console.error(
      `Member "${memberKey}" was not found.`
    );

    return;
  }


  /* -----------------------------------------
     BASIC INFORMATION
     ----------------------------------------- */

  memberDetailAvatar.textContent =
    member.avatar;

  memberDetailName.textContent =
    member.name;

  memberDetailRole.textContent =
    member.role;


  /* -----------------------------------------
     MEMBER IMAGE
     ----------------------------------------- */

  const image =
    document.createElement('img');

  image.src = member.image;

  image.alt =
    `${member.name} profile photo`;

  image.loading = 'lazy';

  memberDetailImage.replaceChildren(
    image
  );


  /* -----------------------------------------
     SKILLS
     ----------------------------------------- */

  createMemberTags(
    memberDetailSkills,
    member.skills
  );


  /* -----------------------------------------
     KNOWLEDGE
     ----------------------------------------- */

  createMemberTags(
    memberDetailKnowledge,
    member.knowledge
  );


  /* -----------------------------------------
     TECHNOLOGIES
     ----------------------------------------- */

  createMemberTags(
    memberDetailTechnologies,
    member.technologies
  );


  /* -----------------------------------------
     CONTRIBUTION
     ----------------------------------------- */

  memberDetailContribution.textContent =
    member.contribution;


  /* -----------------------------------------
     QUOTE
     ----------------------------------------- */

  memberDetailQuote.textContent =
    `"${member.quote}"`;


  /* -----------------------------------------
     OPEN PANEL
     ----------------------------------------- */

  memberOverlay.classList.add('active');

  memberPanel.classList.add('active');

  document.body.classList.add(
    'member-open'
  );

}


/* =========================================================
   CLOSE MEMBER DETAIL
   ========================================================= */

function closeMemberDetail() {

  memberOverlay.classList.remove(
    'active'
  );

  memberPanel.classList.remove(
    'active'
  );

  document.body.classList.remove(
    'member-open'
  );

}


/* =========================================================
   MEMBER CARD CLICK
   ========================================================= */

memberCards.forEach((card) => {

  card.addEventListener('click', () => {

    const memberKey =
      card.dataset.member;

    openMemberDetail(memberKey);

  });

});


/* =========================================================
   CLOSE BUTTON
   ========================================================= */

memberClose.addEventListener(
  'click',
  closeMemberDetail
);


/* =========================================================
   CLICK OVERLAY TO CLOSE
   ========================================================= */

memberOverlay.addEventListener(
  'click',
  closeMemberDetail
);


/* =========================================================
   ESCAPE KEY
   ========================================================= */

document.addEventListener(
  'keydown',
  (event) => {

    if (
      event.key === 'Escape' &&
      memberPanel.classList.contains('active')
    ) {

      closeMemberDetail();

    }

  }
);