(function() {
  // DATA STORAGE
  // This array holds all active production items
  let productionItems = [];
  // This array holds all completed/historical items
  let historyItems = [];

  // DOM ELEMENTS
  // containers
  const productionContainer = document.getElementById("productionContainer"); // container production
  const historyContainer = document.getElementById("historyContainer"); // container history
  // functions
  const addItemBtn = document.getElementById("addItemBtn"); // add item button
  const addModal = document.getElementById("addModal"); // pop up modal
  const saveItemBtn = document.getElementById("saveItem"); // save item button in modal
  const cancelItemBtn = document.getElementById("cancelItem"); // cancel button in modal
  // add item pop up inputs
  const itemNameInput = document.getElementById("itemName"); // input for item name
  const itemQuantityInput = document.getElementById("itemQuantity"); // input for item quantity
  // main pages
  const productionPage = document.getElementById("productionPage"); // main production page
  const historyPage = document.getElementById("historyPage"); // main history page
  //buttons
  const navProduction = document.getElementById("navProduction"); // navigation button for production
  const navHistory = document.getElementById("navHistory"); // navigation button for history
  const logoutBtn = document.getElementById("logoutBtn"); // logout button

  // MODAL HANDLING
  function openModal() {        // open modal function
    addModal.style.display = "flex";
    itemNameInput.value = "";
    itemQuantityInput.value = "1";
    itemNameInput.focus();
  }

  function closeModal() {       // close modal function
    addModal.style.display = "none";
    itemNameInput.value = "";
    itemQuantityInput.value = "";
  }

  addItemBtn.addEventListener("click", openModal);      // open
  cancelItemBtn.addEventListener("click", closeModal);    //close
  addModal.addEventListener("click", function(e) { // if nag click outside ng box mag c-close
    if (e.target === addModal) closeModal();
  });

  // ADD NEW ITEM
  saveItemBtn.addEventListener("click", function() {        // dito yung pag add ng item sa production
    const name = itemNameInput.value.trim();
    const quantityValue = itemQuantityInput.value.trim();

    if (name === "" || quantityValue === "") { //validation if empty both
      alert("Please fill in both item name and quantity.");
      return;
    }

    const quantityNumber = Number(quantityValue); // validation if ever man na negative ang mailagay
    if (isNaN(quantityNumber) || quantityNumber <= 0) {
      alert("Quantity must be a positive number.");
      return;
    }

    const newItem = {       // Create a new iem
      id: Date.now(),   // yung sa date after mo i add yung item sa production
      name: name,
      quantity: quantityNumber,
      date: new Date().toLocaleDateString("en-US", {
        year: "numeric",
        month: "short",
        day: "numeric"
      })
    };
    // eto yung mag a-add ng new item sa list
    productionItems.push(newItem);      // pag nag add ng items
    renderProduction(); // Refresh the production display
    closeModal(); // Close the modal after saving
  });

  // RENDER PRODUCTION
  function renderProduction() {
    productionContainer.innerHTML = "";
    
    // Show empty state if no items exist
    if (productionItems.length === 0) {     // if empty pa
      productionContainer.innerHTML = '<div class="empty-state">No production items.</div>';
      return;
    }

    // Create and display a card for each production item
    productionItems.forEach(item => {       // info ng items
      const card = document.createElement("div");
      card.className = "item-card";
      card.innerHTML = `
        <div class="item-info">
          <h3>${escapeHTML(item.name)}</h3>
          <p>Quantity: <strong>${item.quantity}</strong></p>
          <small>Added: ${item.date}</small>
        </div>
        <button class="done-btn">COMPLETE</button>
      `;

      // Add click event to the complete button for this item
      card.querySelector(".done-btn").addEventListener("click", function(e) {       // button pag complete na
        e.stopPropagation();
        completeItem(item.id);
      });

      productionContainer.appendChild(card);
    });
  }

  // COMPLETE ITEM
  // if complete na yung product is malilipat sa history
  function completeItem(id) {
    // Find the item to complete
    const finishedItem = productionItems.find(item => item.id === id);
    if (!finishedItem) return;

    // Add completion date to the item
    const completedItem = {
      ...finishedItem,
      completedDate: new Date().toLocaleDateString("en-US", {
        year: "numeric",
        month: "short",
        day: "numeric"
      })
    };

    // Move item from production to history
    historyItems.push(completedItem);
    productionItems = productionItems.filter(item => item.id !== id);

    // Update both views
    renderProduction();
    renderHistory();
  }

  // ---------- RENDER HISTORY ----------
  // Function to display all completed/historical items
  function renderHistory() {
    historyContainer.innerHTML = "";

    // empty if wala pang nakalagay sa history
    if (historyItems.length === 0) {
      historyContainer.innerHTML = '<div class="empty-state">No completed items.</div>';
      return;
    }

    // Create and display a card for each history item
    historyItems.forEach(item => {
      const card = document.createElement("div");
      card.className = "item-card";
      card.innerHTML = `
        <div class="item-info">
          <h3>${escapeHTML(item.name)}</h3>
          <p>Quantity: <strong>${item.quantity}</strong></p>
          <small>Completed: ${item.completedDate}</small>
        </div>
        <div style="color: var(--sage-dark); font-weight: bold; font-size: 1.2rem;">&#10003;</div>
      `;
      historyContainer.appendChild(card);
    });
  }

  // PAGE NAVIGATION
  // Event listener for switching to the Production page
  navProduction.addEventListener("click", function() {
    productionPage.classList.remove("hidden");
    historyPage.classList.add("hidden");
    navProduction.classList.add("active");
    navHistory.classList.remove("active");
  });

  // Event listener for switching to the History page
  navHistory.addEventListener("click", function() {
    historyPage.classList.remove("hidden");
    productionPage.classList.add("hidden");
    navHistory.classList.add("active");
    navProduction.classList.remove("active");
  });

  // ---------- HELPER ----------
  // Helper function to escape HTML characters to prevent XSS attacks
  function escapeHTML(str) {
    const div = document.createElement("div");
    div.appendChild(document.createTextNode(str));
    return div.innerHTML;
  }

  // ---------- INITIAL RENDER ----------
  // Render both production and history views on page load
  renderProduction();
  renderHistory();
})();