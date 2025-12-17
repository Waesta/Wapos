/**
 * Google Maps Delivery Integration
 * Handles Places API, Routes API, and Maps JavaScript API for delivery operations
 */

class GoogleMapsDelivery {
    constructor(config) {
        this.config = {
            mapsApiKey: config.mapsApiKey || '',
            placesApiKey: config.placesApiKey || '',
            routesApiKey: config.routesApiKey || '',
            businessLocation: config.businessLocation || { lat: -1.286389, lng: 36.817223 },
            ...config
        };
        
        this.map = null;
        this.markers = {};
        this.routes = {};
        this.autocompleteService = null;
        this.placesService = null;
        this.directionsService = null;
        this.directionsRenderer = null;
        this.geocoder = null;
        this.loaded = false;
        this.loadPromise = null;
    }

    /**
     * Load Google Maps JavaScript API
     */
    async loadMapsAPI() {
        if (this.loadPromise) {
            return this.loadPromise;
        }

        if (window.google && window.google.maps) {
            this.loaded = true;
            this.initializeServices();
            return Promise.resolve(window.google.maps);
        }

        if (!this.config.mapsApiKey) {
            throw new Error('Google Maps API key not configured');
        }

        this.loadPromise = new Promise((resolve, reject) => {
            const script = document.createElement('script');
            script.src = `https://maps.googleapis.com/maps/api/js?key=${encodeURIComponent(this.config.mapsApiKey)}&libraries=places,geometry,drawing&callback=initGoogleMaps`;
            script.async = true;
            script.defer = true;

            window.initGoogleMaps = () => {
                this.loaded = true;
                this.initializeServices();
                resolve(window.google.maps);
            };

            script.onerror = () => {
                reject(new Error('Failed to load Google Maps API'));
            };

            document.head.appendChild(script);
        });

        return this.loadPromise;
    }

    /**
     * Initialize Google Maps services
     */
    initializeServices() {
        if (!window.google || !window.google.maps) return;

        this.autocompleteService = new google.maps.places.AutocompleteService();
        this.geocoder = new google.maps.Geocoder();
        this.directionsService = new google.maps.DirectionsService();
    }

    /**
     * Initialize map on a DOM element
     */
    async initializeMap(elementId, options = {}) {
        await this.loadMapsAPI();

        const element = document.getElementById(elementId);
        if (!element) {
            throw new Error(`Element with id '${elementId}' not found`);
        }

        const defaultOptions = {
            center: this.config.businessLocation,
            zoom: 13,
            mapTypeControl: false,
            streetViewControl: false,
            fullscreenControl: true,
            zoomControl: true
        };

        this.map = new google.maps.Map(element, { ...defaultOptions, ...options });
        this.placesService = new google.maps.places.PlacesService(this.map);

        return this.map;
    }

    /**
     * Address autocomplete with Places API
     */
    async autocompleteAddress(input, options = {}) {
        await this.loadMapsAPI();

        return new Promise((resolve, reject) => {
            const request = {
                input: input,
                componentRestrictions: options.country ? { country: options.country } : undefined,
                location: options.location ? new google.maps.LatLng(options.location.lat, options.location.lng) : undefined,
                radius: options.radius || 50000
            };

            this.autocompleteService.getPlacePredictions(request, (predictions, status) => {
                if (status === google.maps.places.PlacesServiceStatus.OK) {
                    resolve(predictions || []);
                } else if (status === google.maps.places.PlacesServiceStatus.ZERO_RESULTS) {
                    resolve([]);
                } else {
                    reject(new Error(`Autocomplete failed: ${status}`));
                }
            });
        });
    }

    /**
     * Get place details by place_id
     */
    async getPlaceDetails(placeId) {
        await this.loadMapsAPI();

        if (!this.placesService) {
            throw new Error('Places service not initialized. Call initializeMap first.');
        }

        return new Promise((resolve, reject) => {
            this.placesService.getDetails(
                {
                    placeId: placeId,
                    fields: ['formatted_address', 'geometry', 'address_components', 'name']
                },
                (place, status) => {
                    if (status === google.maps.places.PlacesServiceStatus.OK) {
                        resolve({
                            formatted_address: place.formatted_address,
                            latitude: place.geometry.location.lat(),
                            longitude: place.geometry.location.lng(),
                            address_components: place.address_components,
                            name: place.name
                        });
                    } else {
                        reject(new Error(`Place details failed: ${status}`));
                    }
                }
            );
        });
    }

    /**
     * Geocode address to coordinates
     */
    async geocodeAddress(address) {
        await this.loadMapsAPI();

        return new Promise((resolve, reject) => {
            this.geocoder.geocode({ address: address }, (results, status) => {
                if (status === 'OK' && results.length > 0) {
                    const result = results[0];
                    resolve({
                        formatted_address: result.formatted_address,
                        latitude: result.geometry.location.lat(),
                        longitude: result.geometry.location.lng(),
                        place_id: result.place_id,
                        address_components: result.address_components
                    });
                } else {
                    reject(new Error(`Geocoding failed: ${status}`));
                }
            });
        });
    }

    /**
     * Add marker to map
     */
    addMarker(id, position, options = {}) {
        if (!this.map) {
            throw new Error('Map not initialized');
        }

        const marker = new google.maps.Marker({
            map: this.map,
            position: position,
            title: options.title || '',
            icon: options.icon || undefined,
            draggable: options.draggable || false,
            animation: options.animation || null
        });

        if (options.infoWindow) {
            const infoWindow = new google.maps.InfoWindow({
                content: options.infoWindow
            });

            marker.addListener('click', () => {
                infoWindow.open(this.map, marker);
            });
        }

        if (options.onClick) {
            marker.addListener('click', options.onClick);
        }

        if (options.onDragEnd) {
            marker.addListener('dragend', (event) => {
                options.onDragEnd({
                    lat: event.latLng.lat(),
                    lng: event.latLng.lng()
                });
            });
        }

        this.markers[id] = marker;
        return marker;
    }

    /**
     * Remove marker from map
     */
    removeMarker(id) {
        if (this.markers[id]) {
            this.markers[id].setMap(null);
            delete this.markers[id];
        }
    }

    /**
     * Update marker position
     */
    updateMarker(id, position) {
        if (this.markers[id]) {
            this.markers[id].setPosition(position);
        }
    }

    /**
     * Clear all markers
     */
    clearMarkers() {
        Object.values(this.markers).forEach(marker => marker.setMap(null));
        this.markers = {};
    }

    /**
     * Compute route between two points
     */
    async computeRoute(origin, destination, options = {}) {
        await this.loadMapsAPI();

        return new Promise((resolve, reject) => {
            const request = {
                origin: origin,
                destination: destination,
                travelMode: options.travelMode || google.maps.TravelMode.DRIVING,
                drivingOptions: {
                    departureTime: options.departureTime || new Date(),
                    trafficModel: options.trafficModel || google.maps.TrafficModel.BEST_GUESS
                },
                avoidHighways: options.avoidHighways || false,
                avoidTolls: options.avoidTolls || false,
                avoidFerries: options.avoidFerries || false,
                provideRouteAlternatives: options.alternatives || false
            };

            this.directionsService.route(request, (result, status) => {
                if (status === 'OK') {
                    const route = result.routes[0];
                    const leg = route.legs[0];

                    resolve({
                        distance_meters: leg.distance.value,
                        distance_text: leg.distance.text,
                        duration_seconds: leg.duration.value,
                        duration_text: leg.duration.text,
                        duration_in_traffic: leg.duration_in_traffic ? leg.duration_in_traffic.value : null,
                        start_address: leg.start_address,
                        end_address: leg.end_address,
                        polyline: route.overview_polyline,
                        bounds: route.bounds,
                        directionsResult: result
                    });
                } else {
                    reject(new Error(`Route computation failed: ${status}`));
                }
            });
        });
    }

    /**
     * Display route on map
     */
    displayRoute(routeId, directionsResult, options = {}) {
        if (!this.map) {
            throw new Error('Map not initialized');
        }

        // Remove existing route if present
        if (this.routes[routeId]) {
            this.routes[routeId].setMap(null);
        }

        const renderer = new google.maps.DirectionsRenderer({
            map: this.map,
            directions: directionsResult,
            suppressMarkers: options.suppressMarkers || false,
            preserveViewport: options.preserveViewport || false,
            polylineOptions: {
                strokeColor: options.strokeColor || '#4285F4',
                strokeOpacity: options.strokeOpacity || 0.7,
                strokeWeight: options.strokeWeight || 5
            }
        });

        this.routes[routeId] = renderer;
        return renderer;
    }

    /**
     * Remove route from map
     */
    removeRoute(routeId) {
        if (this.routes[routeId]) {
            this.routes[routeId].setMap(null);
            delete this.routes[routeId];
        }
    }

    /**
     * Clear all routes
     */
    clearRoutes() {
        Object.values(this.routes).forEach(route => route.setMap(null));
        this.routes = {};
    }

    /**
     * Optimize route for multiple waypoints
     */
    async optimizeRoute(origin, waypoints, destination, options = {}) {
        await this.loadMapsAPI();

        const waypointObjects = waypoints.map(wp => ({
            location: wp,
            stopover: true
        }));

        return new Promise((resolve, reject) => {
            const request = {
                origin: origin,
                destination: destination || origin,
                waypoints: waypointObjects,
                optimizeWaypoints: true,
                travelMode: google.maps.TravelMode.DRIVING,
                drivingOptions: {
                    departureTime: new Date(),
                    trafficModel: google.maps.TrafficModel.BEST_GUESS
                }
            };

            this.directionsService.route(request, (result, status) => {
                if (status === 'OK') {
                    const route = result.routes[0];
                    const optimizedOrder = route.waypoint_order;

                    let totalDistance = 0;
                    let totalDuration = 0;

                    route.legs.forEach(leg => {
                        totalDistance += leg.distance.value;
                        totalDuration += leg.duration.value;
                    });

                    resolve({
                        optimized_order: optimizedOrder,
                        total_distance_meters: totalDistance,
                        total_duration_seconds: totalDuration,
                        legs: route.legs,
                        directionsResult: result
                    });
                } else {
                    reject(new Error(`Route optimization failed: ${status}`));
                }
            });
        });
    }

    /**
     * Fit map bounds to show all markers
     */
    fitBounds(locations) {
        if (!this.map) return;

        const bounds = new google.maps.LatLngBounds();
        locations.forEach(location => {
            bounds.extend(new google.maps.LatLng(location.lat, location.lng));
        });

        this.map.fitBounds(bounds);
    }

    /**
     * Calculate distance between two points (Haversine)
     */
    calculateDistance(point1, point2) {
        const R = 6371; // Earth's radius in km
        const dLat = this.toRad(point2.lat - point1.lat);
        const dLng = this.toRad(point2.lng - point1.lng);
        
        const a = Math.sin(dLat / 2) * Math.sin(dLat / 2) +
                  Math.cos(this.toRad(point1.lat)) * Math.cos(this.toRad(point2.lat)) *
                  Math.sin(dLng / 2) * Math.sin(dLng / 2);
        
        const c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));
        const distance = R * c;
        
        return distance;
    }

    toRad(degrees) {
        return degrees * (Math.PI / 180);
    }

    /**
     * Setup address autocomplete on input field
     */
    async setupAddressAutocomplete(inputElement, options = {}) {
        await this.loadMapsAPI();

        const autocomplete = new google.maps.places.Autocomplete(inputElement, {
            fields: ['formatted_address', 'geometry', 'address_components', 'place_id'],
            types: options.types || ['geocode'],
            componentRestrictions: options.country ? { country: options.country } : undefined
        });

        if (options.bounds) {
            autocomplete.setBounds(options.bounds);
        }

        autocomplete.addListener('place_changed', () => {
            const place = autocomplete.getPlace();

            if (!place.geometry) {
                if (options.onError) {
                    options.onError('No details available for input: ' + place.name);
                }
                return;
            }

            const result = {
                formatted_address: place.formatted_address,
                latitude: place.geometry.location.lat(),
                longitude: place.geometry.location.lng(),
                place_id: place.place_id,
                address_components: place.address_components
            };

            if (options.onPlaceSelected) {
                options.onPlaceSelected(result);
            }
        });

        return autocomplete;
    }

    /**
     * Get current location using browser geolocation
     */
    async getCurrentLocation() {
        return new Promise((resolve, reject) => {
            if (!navigator.geolocation) {
                reject(new Error('Geolocation not supported'));
                return;
            }

            navigator.geolocation.getCurrentPosition(
                (position) => {
                    resolve({
                        lat: position.coords.latitude,
                        lng: position.coords.longitude,
                        accuracy: position.coords.accuracy
                    });
                },
                (error) => {
                    reject(new Error(`Geolocation error: ${error.message}`));
                },
                {
                    enableHighAccuracy: true,
                    timeout: 10000,
                    maximumAge: 0
                }
            );
        });
    }

    /**
     * Create custom marker icon
     */
    createMarkerIcon(options = {}) {
        return {
            url: options.url || '',
            scaledSize: new google.maps.Size(options.width || 32, options.height || 32),
            origin: new google.maps.Point(0, 0),
            anchor: new google.maps.Point(options.anchorX || 16, options.anchorY || 32)
        };
    }

    /**
     * Destroy and cleanup
     */
    destroy() {
        this.clearMarkers();
        this.clearRoutes();
        this.map = null;
        this.loaded = false;
    }
}

// Export for use
if (typeof module !== 'undefined' && module.exports) {
    module.exports = GoogleMapsDelivery;
}
