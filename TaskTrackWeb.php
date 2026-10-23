<?php
$gingaProducts = require __DIR__ . '/config/ginga_products.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="description" content="Meet Ginga: explore our ginger, turmeric, fruit, and tea beverage range and learn how our production team brings each product from cooking to packaging.">
  <title>Ginga | A closer look at our products and production</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Playfair+Display:wght@600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="css/GingaWebsite.css?v=<?= filemtime(__DIR__ . '/css/GingaWebsite.css'); ?>">
</head>
<body>
    <?php include __DIR__ . '/config/mobile_refresh.php'; ?>
  <header class="site-header">
    <nav class="navbar" id="navbar" aria-label="Main navigation">
      <a class="brand" href="#home" aria-label="Ginga home">
        <span class="brand-mark" aria-hidden="true">G</span>
        <span>ginga<span class="brand-period">.</span></span>
      </a>
      <button class="nav-toggle" id="navToggle" type="button" aria-label="Open navigation menu" aria-expanded="false" aria-controls="navLinks">
        <span></span><span></span><span></span>
      </button>
      <div class="nav-links" id="navLinks">
        <a class="nav-link active" href="#home">Home</a>
        <a class="nav-link" href="#products">Our products</a>
        <a class="nav-link" href="#production">Production</a>
        <a class="nav-link" href="#about">About Ginga</a>
        <a class="nav-login" href="index.php">Employee login</a>
      </div>
    </nav>
  </header>

  <main>
    <section class="hero" id="home">
      <div class="hero-content">
        <span class="eyebrow"><span class="eyebrow-dot"></span> A taste of Ginga</span>
        <h1>Good things<br>start with <em>ginger.</em></h1>
        <p class="hero-copy">Discover Ginga's range of ginger, turmeric, fruit, and tea beverages—and the people and production stages behind every pack.</p>
        <div class="hero-actions">
          <a class="button button-primary" href="#products">Discover our products <span aria-hidden="true">→</span></a>
          <a class="text-link" href="#production">See how we make them <span aria-hidden="true">↓</span></a>
        </div>
        <div class="hero-note"><span aria-hidden="true">✳</span> Made with care, prepared for everyday moments</div>
      </div>
      <div class="hero-art" aria-label="Illustration of ginger and a warm drink" role="img">
        <div class="sun-disc"></div>
        <div class="leaf leaf-one"></div>
        <div class="leaf leaf-two"></div>
        <div class="ginger-root ginger-main"></div>
        <div class="ginger-root ginger-small"></div>
        <div class="cup">
          <div class="cup-drink"></div>
          <div class="cup-handle"></div>
        </div>
        <span class="art-caption">Ginger, made inviting</span>
      </div>
    </section>

    <section class="products-section page-section" id="products">
      <div class="section-heading">
        <div>
          <span class="eyebrow"><span class="eyebrow-dot"></span> The Ginga range</span>
          <h2>Find your Ginga favorite.</h2>
        </div>
        <p>Explore the products featured in our production range, from ginger and turmeric blends to fruit and herbal teas.</p>
      </div>
      <div class="product-grid">
        <?php foreach ($gingaProducts as $index => $product): ?>
          <article class="product-card product-card-<?= ($index % 4) + 1; ?>">
            <div class="product-art" aria-hidden="true">
              <span class="product-art-orbit"></span>
              <span class="product-art-leaf"></span>
              <span class="product-pack">
                <span class="pack-brand">ginga</span>
                <span class="pack-line"></span>
                <span class="pack-name"><?= htmlspecialchars($product, ENT_QUOTES, 'UTF-8'); ?></span>
                <span class="pack-seal">BOTANICAL<br>BLEND</span>
              </span>
            </div>
            <div class="product-card-copy">
              <span class="product-number"><?= str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT); ?></span>
              <h3><?= htmlspecialchars($product, ENT_QUOTES, 'UTF-8'); ?></h3>
              <p>A Ginga beverage from our production range.</p>
            </div>
          </article>
        <?php endforeach; ?>
      </div>
      <p class="catalog-note">Product names shown here are the same choices available to our production team.</p>
    </section>

    <section class="production-section page-section" id="production">
      <div class="production-intro">
        <span class="eyebrow eyebrow-light"><span class="eyebrow-dot"></span> From plan to pack</span>
        <h2>Every batch is a team effort.</h2>
        <p>Ginga production is coordinated in clear stages, with team members working together to prepare products, complete packaging, and get finished goods ready for delivery.</p>
      </div>
      <div class="production-steps">
        <article class="production-step">
          <span class="step-index">01</span>
          <span class="step-icon" aria-hidden="true">✳</span>
          <h3>Plan the batch</h3>
          <p>Product tasks are organized with quantities, targets, and due dates.</p>
        </article>
        <article class="production-step">
          <span class="step-index">02</span>
          <span class="step-icon" aria-hidden="true">◒</span>
          <h3>Prepare the product</h3>
          <p>The cooking team carries out its assigned production work.</p>
        </article>
        <article class="production-step">
          <span class="step-index">03</span>
          <span class="step-icon" aria-hidden="true">▤</span>
          <h3>Pack with care</h3>
          <p>Packaging follows after cooking is marked complete.</p>
        </article>
        <article class="production-step">
          <span class="step-index">04</span>
          <span class="step-icon" aria-hidden="true">↗</span>
          <h3>Prepare for delivery</h3>
          <p>Finished products move into the delivery workflow.</p>
        </article>
      </div>
      <a class="button button-light" href="#about">More about Ginga <span aria-hidden="true">→</span></a>
    </section>

    <section class="about-section page-section" id="about">
      <div class="about-copy">
        <span class="eyebrow"><span class="eyebrow-dot"></span> About Ginga</span>
        <h2>A beverage range with ginger at its heart.</h2>
        <p>Ginga is a beverage brand featuring ginger, turmeric, fruit, and herbal tea products. Our range brings together familiar botanical ingredients in convenient drink options.</p>
        <p>This website introduces Ginga's products and the production work behind them. The names in the catalog are connected to the product choices used by the production team.</p>
        <a class="text-link text-link-dark" href="#products">Browse the Ginga range <span aria-hidden="true">↑</span></a>
      </div>
      <div class="about-highlight">
        <span class="highlight-spark" aria-hidden="true">✳</span>
        <span class="highlight-label">The Ginga range</span>
        <strong>Ginger.<br>Turmeric.<br>Tea &amp; more.</strong>
        <span class="highlight-foot">A product lineup made for everyday enjoyment.</span>
      </div>
    </section>

    <section class="careers-band" aria-labelledby="careersTitle">
      <div>
        <span class="eyebrow eyebrow-light"><span class="eyebrow-dot"></span> Join the team</span>
        <h2 id="careersTitle">Help bring Ginga products to life.</h2>
        <p>Interested in being part of the team? Submit an application to get started.</p>
      </div>
      <a class="button button-light" href="appli_form.php">Apply to Ginga <span aria-hidden="true">→</span></a>
    </section>
  </main>

  <footer class="site-footer">
    <a class="brand brand-footer" href="#home"><span class="brand-mark" aria-hidden="true">G</span><span>ginga<span class="brand-period">.</span></span></a>
    <span>Ginga products &amp; production</span>
    <a href="index.php">Employee login</a>
  </footer>
  <script src="js/TaskTrackWeb.js?v=<?= filemtime(__DIR__ . '/js/TaskTrackWeb.js'); ?>"></script>
</body>
</html>
