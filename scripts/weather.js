
(function () {
    var API_KEY = '97ad251d52056557f7e7ba7c37ef88be';
    function iconEmoji(code) {
        if (!code) return '🌡️';
        var id = code.slice(0, 2);
        var map = {
            '01': '☀️',
            '02': '🌤️',
            '03': '🌥️',
            '04': '☁️',
            '09': '🌧️',
            '10': '🌦️',
            '11': '⛈️',
            '13': '❄️',
            '50': '🌫️'
        };
        return map[id] || '🌡️';
    }

    function renderWeather(data) {
        var widget = document.getElementById('weather-widget');
        var temp     = Math.round(data.main.temp);
        var desc     = data.weather[0].description;
        var city     = data.name + ', ' + data.sys.country;
        var icon     = iconEmoji(data.weather[0].icon);
        var humidity = data.main.humidity;
        var feels    = Math.round(data.main.feels_like);

        widget.className = '';
        widget.innerHTML =
            '<span class="w-icon">' + icon + '</span>' +
            '<span class="w-temp">' + temp + '°C</span>' +
            '<span class="w-info">' +
                '<span class="w-desc">' + desc + ' &nbsp;·&nbsp; Feels like ' + feels + '°C</span>' +
                '<span class="w-city">📍 ' + city + ' &nbsp;·&nbsp; 💧 ' + humidity + '% humidity</span>' +
            '</span>';
    }

    function renderError(msg) {
        var widget = document.getElementById('weather-widget');
        widget.className = 'error';
        widget.innerHTML = '<span class="w-icon">⚠️</span><span>' + msg + '</span>';
    }

    function fetchByCity(city) {
        var url = 'https://api.openweathermap.org/data/2.5/weather' +
                  '?q=' + encodeURIComponent(city) +
                  '&units=metric&appid=' + API_KEY;
        fetch(url)
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (data.cod !== 200) { renderError('Weather unavailable'); return; }
                renderWeather(data);
            })
            .catch(function () { renderError('Network error'); });
    }
    fetchByCity('Ahmedabad');
}());
