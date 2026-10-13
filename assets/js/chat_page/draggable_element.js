function fixed_layout_draggable() {
    const draggableDivs = document.querySelectorAll(".fixed_draggable_layout");
    let offsetX, offsetY, isDragging = false, selectedDiv = null;

    function onPointerDown(event) {
        // Ignore right-clicks or clicks on close buttons
        if (event.button !== 0 || event.target.closest('.close_window_icon')) return;

        selectedDiv = event.currentTarget;
        const rect = selectedDiv.getBoundingClientRect();
        
        offsetX = event.clientX - rect.left;
        offsetY = event.clientY - rect.top;
        isDragging = true;

        selectedDiv.style.cursor = "grabbing";
        
        // This is the magic line: it tells the browser "keep sending 
        // mouse/touch events to this div even if the cursor leaves it"
        selectedDiv.setPointerCapture(event.pointerId);
    }

    function onPointerMove(event) {
        if (!isDragging || !selectedDiv) return;

        const newX = event.clientX - offsetX;
        const newY = event.clientY - offsetY;
        
        const maxX = window.innerWidth - selectedDiv.clientWidth;
        const maxY = window.innerHeight - selectedDiv.clientHeight;

        selectedDiv.style.left = Math.min(maxX, Math.max(0, newX)) + "px";
        selectedDiv.style.top = Math.min(maxY, Math.max(0, newY)) + "px";
    }

    function onPointerUp(event) {
        if (!selectedDiv) return;
        
        isDragging = false;
        selectedDiv.style.cursor = "grab";
        // Release the mouse lock
        selectedDiv.releasePointerCapture(event.pointerId);
        selectedDiv = null;
    }

    draggableDivs.forEach((div) => {
        div.style.touchAction = "none"; // Critical: Prevents mobile scrolling while dragging
        div.style.position = "fixed";   // Ensure it's movable
        
        // Use Pointer Events (Handles Mouse AND Touch)
        div.addEventListener("pointerdown", onPointerDown);
        div.addEventListener("pointermove", onPointerMove);
        div.addEventListener("pointerup", onPointerUp);
        div.addEventListener("pointercancel", onPointerUp); // Handle unexpected interruptions
        
        // Prevent the "Ghost Image" stickiness
        div.addEventListener("dragstart", (e) => e.preventDefault());
    });
}