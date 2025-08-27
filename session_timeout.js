(function() {
    const INACTIVITY_TIMEOUT = 5 * 60 * 1000; // 5 minutes in milliseconds
    let logoutTimer;

    function resetTimer() {
        clearTimeout(logoutTimer);
        logoutTimer = setTimeout(() => {
            // Redirect to logout page
            window.location.href = 'logout.php';
        }, INACTIVITY_TIMEOUT);
    }

    function init() {
        // List of events that indicate user activity
        const activityEvents = [
            'mousemove', 'keydown', 'mousedown', 'touchstart', 'scroll'
        ];

        // Add event listeners to reset the timer on user activity
        activityEvents.forEach(event => {
            document.addEventListener(event, resetTimer, true);
        });

        // Start the initial timer
        resetTimer();
    }

    // Initialize the inactivity tracker
    init();
})();
