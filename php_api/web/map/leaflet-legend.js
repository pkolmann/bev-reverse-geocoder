L.Control.Legend = L.Control.extend({
    options: {
        position: 'bottomright',
        collapsed: true,
        buttonHtml: 'legend'
    },

    onRemove: function () {
        this._map.off('click', this.collapse, this);

        this._container = null;
    },

    onAdd: function (map) {
        this._map = map;

        return this._initLayout();
    },

    _initLayout: function() {
        var className = 'leaflet-legend',
            container = this._container = L.DomUtil.create('div', className),
            collapsed = this.options.collapsed;

        // makes this work on IE10 Touch devices by stopping it from firing a mouseout event when the touch is released
        container.setAttribute('aria-haspopup', true);

        L.DomEvent.disableClickPropagation(container);
        L.DomEvent.disableScrollPropagation(container);

        if (collapsed) {
            this._map.on('click', this.collapse, this);

            if (!L.Browser.android) {
                L.DomEvent.on(container, {
                    'mouseover': this.expand,
                    'mouseout': this.collapse
                }, this);
            }
        }
        var link = L.DomUtil.create('a', className + '-toggle', container);
        link.href = '#';
        link.title = 'legend';
        link.innerHTML = this.options.buttonHtml;

        if (L.Browser.touch) {
            L.DomEvent.on(link, 'click', L.DomEvent.stop);
            L.DomEvent.on(link, 'click', this.expand, this);
        } else {
            L.DomEvent.on(link, 'focus', this.expand, this);
        }

        if (!collapsed) {
            this.expand();
        }

        var list = L.DomUtil.create('div', className + '-list');
        this.options.items.forEach(function (item) {
            var div = L.DomUtil.create('div', className + '-item', list);
            var colorbox = L.DomUtil.create('div', className + '-color', div);
            colorbox.innerHTML = '&nbsp;';
            colorbox.style.backgroundColor = item.color;
            L.DomUtil.create('div', className + '-text', div).innerHTML = item['label'];
        });

        container.appendChild(list);
        return container;
    },

    expand: function() {
        L.DomUtil.addClass(this._container, 'leaflet-legend-expanded');
        return this;
    },

    collapse: function() {
        L.DomUtil.removeClass(this._container, 'leaflet-legend-expanded');
        return this;
    },
});

L.control.legend = function (options) {
    return new L.Control.Legend(options);
};
