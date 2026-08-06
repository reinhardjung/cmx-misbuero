window.cmxGetLocation = function(callback) {
    if (typeof callback !== "function" || !navigator.geolocation) return;

    function formatAddress(payload) {
        var address = payload && payload.address ? payload.address : {};
        var street = [address.road || address.pedestrian || address.footway || address.cycleway || "", address.house_number || ""]
            .join(" ")
            .trim();
        var locality = address.city || address.town || address.village || address.hamlet || address.municipality || "";
        var postal = address.postcode || "";
        var region = address.state || address.county || "";
        var parts = [];

        if (street) parts.push(street);
        if (postal || locality) parts.push([postal, locality].join(" ").trim());
        if (region && parts.indexOf(region) === -1) parts.push(region);

        return parts.join(", ").trim();
    }

    navigator.geolocation.getCurrentPosition(function(pos) {
        var result = {
            lat: pos.coords.latitude,
            lon: pos.coords.longitude,
            address: ""
        };

        if (typeof fetch !== "function") {
            callback(result);
            return;
        }

        var url = "https://nominatim.openstreetmap.org/reverse?format=jsonv2&addressdetails=1&zoom=18&lat="
            + encodeURIComponent(String(result.lat))
            + "&lon="
            + encodeURIComponent(String(result.lon));

        fetch(url, {
            headers: {
                "Accept": "application/json"
            }
        }).then(function(response) {
            return response.ok ? response.json() : null;
        }).then(function(payload) {
            result.address = formatAddress(payload) || String((payload && payload.display_name) || "").trim();
            callback(result);
        }).catch(function() {
            callback(result);
        });
    }, function() {
        callback(null);
    }, {
        enableHighAccuracy: true,
        timeout: 10000,
        maximumAge: 0
    });
};
