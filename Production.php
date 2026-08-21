<?php
    include ('config/connection.php');

    session_start();
?>

<!-- index.html -->
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes" />
  <title>TaskTrack</title>
  <link rel="stylesheet" href="../css/Production.css">
</head>
<body>

<!--infos -->
<header class="navbar-top">
  <div class="navbar-header">
    <img
      src="https://fiverr-res.cloudinary.com/images/t_main1,q_auto,f_auto,q_auto,f_auto/gigs/411225890/original/d19bec69d0ab8dce9548a59a6daca1a9b85cfd0a/draw-high-quailty-pfp-anime-avatar-for-youtube-discord.png"
      class="avatar"> <!-- wala pang id to kasi hindi ko alam pano pinaka function nya with js, backend -->
    <p id="name" class="name">Name here</p>
  </div>
<!-- buttons -->
  <div class="navbar-buttons">
    <button id="navProduction" class="navbar-btn active">PRODUCTION</button>
    <button id="navHistory" class="navbar-btn">HISTORY</button>
  </div>

  <button id="logoutBtn" class="logout-btn">LOG OUT</button>
</header>
<!-- main -->
<section id="productionPage" class="page">
  <div class="item-list">
    <div class="list-header">
      <h1>Production List</h1>
      <button id="addItemBtn" class="add-btn"> ADD ITEM</button>
    </div>
    <div id="productionContainer" class="list-container">
      <div class="empty-state">No production items yet.</div>
    </div>
  </div>
</section>

<!-- if no added items naka show to -->
<section id="historyPage" class="page hidden">
  <div class="item-list">
    <div class="list-header">
      <h1>History</h1>
    </div>
    <div id="historyContainer" class="list-container">
      <div class="empty-state">No completed items.</div>
    </div>
  </div>
</section>

<!-- add item pop up -->
<div id="addModal" class="modal">
  <div class="modal-content">
    <h2>Add New Item</h2>
    <input type="text" id="itemName" placeholder="Item Name" autocomplete="off" />
    <input type="number" id="itemQuantity" placeholder="Quantity" min="1" value="1" />
    <div class="modal-buttons">
      <button id="cancelItem">Cancel</button>
      <button id="saveItem">Save Item</button>
    </div>
  </div>
</div>

<script src="../js/Production.js"></script>
</body>
</html>