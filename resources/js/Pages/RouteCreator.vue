<template>
    <div>
        <div id="mapid"></div>
        <div id="control-container">
            <div id="controls">
                <div>
                    <select v-if="routes.length" v-model="activeRouteIndex" @change="highlightActiveRoute">
                        <option v-for="(route, index) in routes" :key="`route_${index}`" :value="route.index">{{ route.name }}</option>
                    </select>

                    <div id="main-control-buttons">
                        <Button type="button" @click="startRoute" title="Start new route"><i class="fas fa-plus"></i> Start new route</Button>
                        <Button v-if="showExportButton" type="button" @click="exportRoute" title="Export active route"><i class="fas fa-route"></i> Export active route</Button>
                    </div>
                </div>
                <div id="legend">
                    <div 
                        v-for="(route, index) in routes" 
                        :key="`legend_${index}`"
                        class="legend-item"
                        :class="route.index === activeRouteIndex ? 'active' : ''" 
                        :style="`background-color: ${route.color}; border-color: ${route.color}`"
                    >
                        <div :class="route.index === activeRouteIndex ? 'active' : ''">
                            <span>{{ route.name }} - {{ route.distance.toFixed(2) }} km</span>
                            <div class="buttons">
                                <Button type="button" @click="deleteRoute(index)" title="Delete route"><i class="fas fa-trash-alt"></i></Button>
                                <Button v-if="route.index === activeRouteIndex && routes.length > 1" type="button" @click="showMergeInterface = true" title="Prepend route to..."><i class="fas fa-paste"></i></Button>
                                <Button type="button" @click="reverse(index)" title="Reverse route"><i class="fas fa-exchange-alt"></i></Button>
                            </div>
                        </div>
                    </div>
                </div>
                <div id="merge-interface" v-if="showMergeInterface">
                    <h2>Click the route you wish to append the current to</h2>
                    <div v-for="(route, index) in mergeableRoutes" :key="`merging_${index}`">
                        <Button 
                            type="button" 
                            @click="merge(route.index)"
                            :style="`background-color: ${route.color};`"
                        >
                            {{ route.name }} - {{ route.distance.toFixed(2) }} km
                        </Button>
                    </div>
                </div>
                <Dropzone v-else @track-imported="handleTrackImported"/>
            </div>
        </div>
    </div>
</template>

<script>
import calculateDistance, {calculatePointToPointDistance as p2pDistance } from '../libs/distance'
import Button from '../Components/Button'
import Dropzone from '../RouteComponents/Dropzone'
import { Notyf } from 'notyf';
import 'notyf/notyf.min.css';

export default {
    name: 'RouteCreator',
    components: { Button, Dropzone },
    data() {
        return {
            mymap: null,
            accessToken: 'pk.eyJ1IjoicGltaG9vZ2hpZW1zdHJhIiwiYSI6ImNrbnZ1cnRjZDA5Yngyd3Bta3Y2NXMydm0ifQ.eMPCdzzcSvMwIXRgRn3b3Q',
            home: [50.99408, 5.85511],
            routes: [],
            activeRouteIndex: 0,
            occupiedIndices: [],
            colors: ['#ec008c', '#fff100', '#ff8c00', '#e81123', '#68217a', '#00188f', '#00bcf2', '#00b294', '#009e49', '#bad80a'],
            upload: {
                isInitial: true,
                isSaving: false,
                fieldName: 'gpx',
            },
            showMergeInterface: false,
        }
    },
    computed: {
        showExportButton() {
            // if there is at least one route with >1 points
            let showButton = false
            this.routes.forEach(route => {
                if (route.points.length > 1) showButton = true
            })
            return showButton
        },
        firstFreeIndex() {
            return this.occupiedIndices.findIndex(item => item === false)
        },
        activeRoute() {
            return this.routes.find(({index}) => index === this.activeRouteIndex)
        },
        mergeableRoutes() {
            return this.routes.filter(route => route.index !== this.activeRouteIndex)
        },
    },
    methods: {
        initMap() {
            this.mymap = L.map('mapid').setView(this.home, 16)
            L.tileLayer(`https://api.mapbox.com/styles/v1/{id}/tiles/{z}/{x}/{y}?access_token=${this.accessToken}`, {
                attribution: 'Map data &copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors, Imagery © <a href="https://www.mapbox.com/">Mapbox</a>',
                maxZoom: 18,
                id: 'mapbox/streets-v11',
                tileSize: 512,
                zoomOffset: -1,
                accessToken: this.accessToken
            }).addTo(this.mymap)

            // init occupiedIndices: array with false
            this.occupiedIndices = this.colors.map(() => false)

            // Init listener for clicks
            this.mymap.on('click', this.onMapClick)

        },
        onMapClick({latlng}) {
            const route = this.activeRoute
            if (route) {
                // add to current active route
                route.polyline.addLatLng(latlng)
                
                // add a point and make it black, previous last point should become blue, unless it is the starting point
                const circle = L.circle(latlng, {
                    radius: 15, 
                    color: 'black',
                    fillOpacity: 1,
                    bubblingMouseEvents: false
                })
                circle.addTo(this.mymap);
                circle.on('click', this.onPointClick)

                const currentNumberOfPoints = route.points.length
                const currentLastPoint = route.points[currentNumberOfPoints-1]
                if (currentNumberOfPoints > 1) {
                    currentLastPoint.circle.setStyle({color: 'blue'})
                }

                // update distance of route
                const addedDistance = p2pDistance(currentLastPoint.circle.getLatLng(), latlng)
                route.distance += addedDistance

                route.points.push({
                    circle,
                    index: currentNumberOfPoints
                })
            } else {
                // start a route
                this.activeRouteIndex = this.firstFreeIndex

                // add a point and make it white
                const circle = L.circle(latlng, {
                    radius: 15, 
                    color: '#ffffff',
                    fillOpacity: 1,
                    bubblingMouseEvents: false
                })
                circle.addTo(this.mymap);
                circle.on('click', this.onPointClick)

                // firstFreeIndex tenzij alles vol, dan 
                const routeIndex = this.firstFreeIndex > -1 ? this.firstFreeIndex : this.routes.length
                if (this.firstFreeIndex > -1) {
                    this.occupiedIndices[this.firstFreeIndex] = true
                }
                const color = this.colors[routeIndex % this.colors.length]

                const polyline = L.polyline([latlng], {color})
                polyline.addTo(this.mymap);

                const route = {
                    name: `Nieuwe route ${routeIndex}`,
                    distance: 0,
                    index: routeIndex,
                    color,
                    points: [{
                        circle,
                        index: 0,
                    }],
                    polyline
                }

                this.routes.push(route)

                // highlight
                this.highlightActiveRoute()
            }
        },
        onPointClick(event) {
            const pointIndex = this.findCuttingPointIndex(event)
            if (pointIndex) {
                this.cutRoute(pointIndex)
            } else {
                this.showMessage('Point not on active route or on the edge', 'error')
            }
        },
        deleteRoute(index) {
            const route = this.routes[index]

            // remove polyline from the map
            this.mymap.removeLayer(route.polyline)

            // remove click handler from the points and remove points from map
            route.points.forEach(({circle}) => {
                circle.off('click', this.onPointClick)

                this.mymap.removeLayer(circle)
            })

            // remove from this.routes
            this.routes.splice(index, 1)

            // open index in occupied array
            this.occupiedIndices[route.index % this.colors.length] = false

            // set first route active if active route is being deleted
            if (index === this.activeRouteIndex) {
                this.activeRouteIndex = 0
                this.highlightActiveRoute()
            } else {
                this.activeRouteIndex -= 1
            }
        },
        findCuttingPointIndex(event) {
            // find index of the ACTIVE route where this point is part of
            const route = this.activeRoute
            const routePoints = route.polyline.getLatLngs()
            const pointIndex = routePoints.findIndex(point => point.lat === event.latlng.lat && point.lng === event.latlng.lng)

            if (pointIndex > -1) {
                if (pointIndex > 0 && pointIndex < routePoints.length-1) {
                    return pointIndex
                }
            }

            return null
        },
        cutRoute(pointIndex) {
            const route = this.activeRoute
            // remove polyline from the map
            this.mymap.removeLayer(route.polyline)

            // remove click handler from the points, will be added later on
            route.points.forEach(({circle}) => circle.off('click', this.onPointClick))

            // split points
            const route1Points = route.points.filter(point => point.index <= pointIndex)
            const route2Points = route.points.filter(point => point.index > pointIndex)

            // create new polyline from route1Points
            this.updateRouteOnCut(route, route1Points)
            
            // idem for route2Points (first update activeRouteIndex)
            this.addRoute(route2Points)
        },
        updateRouteOnCut(route, points) {
            const polyline = L.polyline(points.map(({circle}) => circle.getLatLng()), {color: route.color})
            polyline.addTo(this.mymap)
            route.polyline = polyline
            route.distance = calculateDistance(polyline.getLatLngs())
            // update the points of the route
            route.points = points
            // add the click events for the individual circles and update colors
            route.points.forEach(({circle}, index, ar) => {
                const color = index === 0 ? '#ffffff' : (index === ar.length-1 ? '#000000' : 'blue')
                circle.on('click', this.onPointClick)
                circle.setStyle({color})
            })
        },
        addRoute(points) {
            // firstFreeIndex tenzij alles vol, dan 
            const routeIndex = this.firstFreeIndex > -1 ? this.firstFreeIndex : this.routes.length
            this.activeRouteIndex = routeIndex
            if (this.firstFreeIndex > -1) {
                this.occupiedIndices[this.firstFreeIndex] = true
            }
            const color = this.colors[routeIndex % this.colors.length]

            const polyline = L.polyline(points.map(({circle}) => circle.getLatLng()), {color})
            polyline.addTo(this.mymap);

            const route = {
                name: `Route ${routeIndex}`,
                distance: calculateDistance(polyline.getLatLngs()),
                index: routeIndex,
                color,
                points: points.map((point, index) => ({
                    ...point,
                    index
                })),
                polyline
            }

            // add the click events for the individual circles and update colors
            route.points.forEach(({circle}, index, ar) => {
                const color = index === 0 ? '#ffffff' : (index === ar.length-1 ? '#000000' : 'blue')
                circle.on('click', this.onPointClick)
                circle.setStyle({color})
            })

            this.routes.push(route)

            this.highlightActiveRoute()
        },
        showMessage(message, type = 'success') {
            (new Notyf({
                duration: 1500,
                position: {
                    x: 'left',
                    y: 'bottom'
                },
            }))[type](message)
        },
        handleTrackImported({track, distance}) {
            // Map track points to floating point and create objects
            const points = track
                .map(point => [parseFloat(point[0]), parseFloat(point[1])])
                .map((point, index, ar) => {
                    // eerste punt wit, laatste zwart
                    const color = index === 0 ? '#ffffff' : (index === ar.length-1 ? '#000000' : 'blue')

                    const circle = L.circle(point, {
                        radius: 15, 
                        color,
                        fillOpacity: 1,
                        bubblingMouseEvents: false
                    })
                    circle.addTo(this.mymap);
                    circle.on('click', this.onPointClick)

                    return {
                        circle,
                        index
                    }
                })

            this.addRoute(points)

            // Fit map to bounds of route
            this.mymap.fitBounds(this.activeRoute.polyline.getBounds());
            // this.mymap.fitBounds(this.routes[this.activeRouteIndex].polyline.getBounds());
            

            this.showMessage(`New track imported (number of points ${track.length}), distance: ${distance}`)
        },
        highlightActiveRoute() {
            this.routes.forEach(route => {
                if (route.index !== this.activeRouteIndex) {
                    route.polyline.setStyle({opacity: 0.4})
                    route.points.forEach(({circle}, index, ar) => {
                        const color = index === 0 ? '#ffffff' : (index === ar.length-1 ? '#000000' : route.color)
                        circle.setStyle({
                            opacity: 0.3,
                            fillOpacity: 0.3,
                            weight: 1,
                            color
                        })
                    })
                } else {
                    route.polyline.setStyle({opacity: 1})
                    route.points.forEach(({circle}, index, ar) => {
                        const color = index === 0 ? '#ffffff' : (index === ar.length-1 ? '#000000' : 'blue')
                        circle.setStyle({
                            opacity: 1,
                            fillOpacity: 1,
                            weight: 3,
                            color
                        })
                    })
                }
            })
        },
        merge(index) {
            const mergedColor = this.activeRoute.color
            const mergedIndex = this.activeRoute.index
            const activeRoutePoints = this.activeRoute.points
            
            const appendRoute = this.routes.find(route => route.index === index)
            if (appendRoute) {
                const appendRoutePoints = appendRoute.points

                // remove both routes from map
                this.mymap.removeLayer(this.activeRoute.polyline)
                this.mymap.removeLayer(appendRoute.polyline)

                // remove both routes from routes array
                this.deleteRoute(index)
                this.deleteRoute(this.routes.findIndex(route => route.index === mergedIndex))

                // create polyline from combined points
                const mergedPoints = activeRoutePoints.concat(appendRoutePoints)
                console.log(mergedPoints[0])
                const polyline = L.polyline(mergedPoints.map(({circle}) => circle.getLatLng()), {color: mergedColor})
                polyline.addTo(this.mymap)

                // add merged points to the map again (removed after deleting both routes)
                mergedPoints.forEach((point, index, ar) => {
                    const color = index === 0 ? '#ffffff' : (index === ar.length-1 ? '#000000' : 'blue')
                    const circle = L.circle(point.circle.getLatLng(), {
                        radius: 15, 
                        color,
                        fillOpacity: 1,
                        bubblingMouseEvents: false
                    })
                    circle.addTo(this.mymap);
                    circle.on('click', this.onPointClick)
                })

                // create new route object
                const route = {
                    name: `Merged route`,
                    distance: calculateDistance(polyline.getLatLngs()),
                    index: mergedIndex,
                    color: mergedColor,
                    points: mergedPoints.map((point, index) => ({
                        ...point,
                        index
                    })),
                    polyline
                }

                this.routes.push(route)

                this.activeRouteIndex = mergedIndex

                this.highlightActiveRoute()

                this.showMergeInterface = false
            } else {
                this.showMessage('No route to append found, merge not possible', 'error')
            }
        },
        reverse(index) {
            const route = this.routes[index]
            // reverse route, reset index and switch start/endpoint colors
            route.points = route.points.reverse().map((point, index, ar) => {
                if (index === 0) point.circle.setStyle({color: '#ffffff'})
                if (index === ar.length-1) point.circle.setStyle({color: '#000000'})
                return {
                    ...point,
                    index
                }
            })

            // rerender polyline given the new order of points
            route.polyline.setLatLngs(route.points.map(({circle}) => circle.getLatLng()), {color: route.color})

            this.showMessage(`Route has been reversed`)
        },
        startRoute() {
            this.activeRouteIndex = this.firstFreeIndex
            this.showMessage('Click on the map to start new route')
        },
        exportRoute() {
            console.log('export, zie AltitudeProfiles project')
            axios.post(`/export-gpx`, {data: this.activeRoute.polyline.getLatLngs()})
        },
    },
    mounted() {
        this.initMap()
    },
}
</script>

<style lang="less" scoped>
#mapid {
    height: 80vh;
}

#control-container {
    box-sizing: border-box;
    height: 20vh;
    background: #ddeeff;
    padding: 10px;

    select {
        width: 100%;
        height: 45px;
    }

    #controls {
        display: grid;
        grid-template-columns: 411px 1fr 1fr;
        grid-gap: 20px;

        #main-control-buttons {
            margin-top: 10px;
            padding: 10px 7px 10px 10px;
            width: 100%;
            background: #65329933;
            box-sizing: border-box;

            button {
                width: 194px;
                margin-right: 3px;
                i.fas {
                    margin-right: 3px;
                }
            }
        }

        #legend {

            .legend-item {
                margin-bottom: 8px;
                padding: 6px 12px;
                width: 350px;

                &.active {
                    border: 1px solid black !important;
                }
            
                div {
                    display: grid;
                    grid-template-columns: 2fr 1fr;
                    align-items: center;
                    font-size: 0.7875rem;

                    .buttons {
                        display: grid;
                        grid-template-columns: repeat(auto-fit, 30px);
                        grid-gap: 6px;
                        justify-content: end;
                        
                        button {
                        justify-self: center;
                        }
                    }
                }
            }
        }
    }
}
</style>