window.cmxGetLocation = function(callback) {
    if (!navigator.geolocation) return;

    navigator.geolocation.getCurrentPosition(function(pos) {
        callback({
            lat: pos.coords.latitude,
            lon: pos.coords.longitude
        });
    });
};
