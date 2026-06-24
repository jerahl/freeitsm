/**
 * Chart.js dark defaults — Zabbix theme
 * ----------------------------------------------------------------------------
 * Chart.js renders axis/legend/title text onto a <canvas>, which CSS cannot
 * reach. This sets readable defaults for the dark theme. Load it AFTER the
 * Chart.js library and BEFORE any `new Chart(...)` call. Safe to include even
 * where charts aren't created — it no-ops if Chart is undefined.
 *
 * Charts that pass explicit colours in their own config still win; this only
 * changes the inherited defaults (tick labels, legend, title, grid lines).
 */
(function () {
    if (typeof Chart === 'undefined' || !Chart.defaults) return;

    var TEXT  = '#cbd5e1';                  // tick labels, legend, title
    var GRID  = 'rgba(120, 138, 163, 0.18)'; // grid lines / borders

    // Top-level defaults cascade to ticks, legend labels and titles.
    Chart.defaults.color = TEXT;
    Chart.defaults.borderColor = GRID;

    // Per-scale grid + ticks (covers every built-in scale type in v4).
    if (Chart.defaults.scales) {
        Object.keys(Chart.defaults.scales).forEach(function (type) {
            var s = Chart.defaults.scales[type];
            if (!s) return;
            if (s.grid)  { s.grid.color = GRID; s.grid.borderColor = GRID; }
            if (s.ticks) { s.ticks.color = TEXT; }
            if (s.angleLines) { s.angleLines.color = GRID; } // radar/polar
            if (s.pointLabels) { s.pointLabels.color = TEXT; }
        });
    }

    // Plugin defaults (legend/title) — explicit in case a build doesn't
    // inherit Chart.defaults.color for these.
    if (Chart.defaults.plugins) {
        var p = Chart.defaults.plugins;
        if (p.legend && p.legend.labels) p.legend.labels.color = TEXT;
        if (p.title) p.title.color = TEXT;
    }
})();
