<template>
    <div>
        <h1 class="font-medium leading-tight text-5xl m-2 text-green-600">Welcome little explorer</h1>

        <div id="mapid" class="border-2 border-green-600"></div>
    </div>
</template>

<script>
import {calculatePointToPointDistance as p2pDistance} from '../libs/distance.js'
import { calculateSquareIndex, calculateSquareMatrixPosition, createGridPositions, createLeafletGridSquares } from '../libs/grid.js'

export default {
    name: 'LittleExplorer',
    components: {},
    data() {
        return {
            // home: [50.99408, 5.85511], // real home
            home: [50.9933, 5.85395], // op het grasveld
            zoomLevel: 14,
            accessToken: 'pk.eyJ1IjoicGltaG9vZ2hpZW1zdHJhIiwiYSI6ImNrbnZ1cnRjZDA5Yngyd3Bta3Y2NXMydm0ifQ.eMPCdzzcSvMwIXRgRn3b3Q',
            mapboxStyleId: 'ckpzbydzn1d0r17k8ci4bxyid',
            latInterval: 0.0009,
            lngInterval: 0.00143,
            gridSize: 60,
            grid: [],
            squares: [],
            routes: [],
            colorScale: [
                '#00FFFF',
                '#00FF80',
                '#00FF00',
                '#80FF00',
                '#FFFF00',
                '#FF8000',
                '#FF0000',
            ],
        }
    },
    computed: {},
    methods: {
        initMap() {
            this.mymap = L.map('mapid').setView(this.home, this.zoomLevel)

            L.tileLayer(`https://api.mapbox.com/styles/v1/pimhooghiemstra/${this.mapboxStyleId}/tiles/{z}/{x}/{y}?access_token=${this.accessToken}`, {
                attribution: 'Map data &copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors, Imagery © <a href="https://www.mapbox.com/">Mapbox</a>',
                maxZoom: 18,
                // id: 'mapbox/basic-v11',
                tileSize: 512,
                zoomOffset: -1,
                accessToken: this.accessToken
            }).addTo(this.mymap)

            // Our home: add a marker
            L.marker(this.home).addTo(this.mymap);

            // ---
            // The grid with squares
            // ---
            // grid contains the (lat, lng) positions of the lower left point of the square. Squares count from bottom left
            // to the top
            this.grid = createGridPositions(this.home, this.latInterval, this.lngInterval, this.gridSize)

            // squares contains the actual leaflet squares
            const squares = createLeafletGridSquares(this.home, this.latInterval, this.lngInterval, this.gridSize)
            L.featureGroup(squares).addTo(this.mymap)
            // add them to the state (squares)
            this.squares = squares

            // ---
            // Tracks (coarsened via RouteCreator2.vue)
            // ---

            // track 1
            const track1 = this.loadTrackFromLocalStorage('track')
            // Filter points too close to home, at start and finish
            .filter((point, index, curArray) => index > 27 && index < curArray.length-3)

            L.polygon(track1, {color: '#333333', opacity: 0.45, fillOpacity: 0}).addTo(this.mymap);
            // add the track to the state (routes)
            this.routes.push(track1)

            // track 2
            const track2 = this.loadTrackFromLocalStorage('track2')
            // Filter points too close to home, at start and finish
            .filter((point, index, curArray) => index > 10 && index < curArray.length - 10)

            L.polygon(track2, {color: '#330000', opacity: 0.45, fillOpacity: 0}).addTo(this.mymap);
            // add the track to the state (routes)
            this.routes.push(track2)


            // ---
            // Color the squares the track touches
            // ---

            // 1. just loop through all track points, don't think about multiple points in a single square (yet)
            const touchedIndicesTrack1 = []
            track1.forEach((point, index, arr) => {
                const {row, column} = calculateSquareMatrixPosition(point, this.lngInterval, this.latInterval, this.grid[0].ar) 
                const squareIndex = calculateSquareIndex(row, column, this.gridSize)

                if (!touchedIndicesTrack1.includes(squareIndex)) {
                    touchedIndicesTrack1.push(squareIndex)
                    this.grid[squareIndex].count = 1
                }
            })

            const touchedIndicesTrack2 = []
            track2.forEach((point, index, arr) => {
                const {row, column} = calculateSquareMatrixPosition(point, this.lngInterval, this.latInterval, this.grid[0].ar) 
                const squareIndex = calculateSquareIndex(row, column, this.gridSize)

                if (!touchedIndicesTrack2.includes(squareIndex)) {
                    touchedIndicesTrack2.push(squareIndex)
                    this.grid[squareIndex].count++
                }
            })

            const gridIndicesWithHits = this.grid.filter(point => point.count > 0).map(point => ({index: point.index, count: point.count}))
            gridIndicesWithHits.forEach((item, index) => {
                let color = this.getColorOfSquareByHits(item.count)

                this.squares[item.index].setStyle({
                    fillOpacity: 0.5,
                    color,
                    // color: this.colorScale[5],
                })
            })

            // ---
            // Give colors to the grid items
            // ---
            /**
             * 1. Loop through points in track
             * 2. Find square containing the point
             * 3. update the 'amount' field of the square
             * 4. set the 'visited_by_track' to true => this way a square can only by visited once per track
             * 5. plot the grid, giving each square the appropriate color based on the amount value (with just one track, this is either red (visited) or background color)
             */
        },
        getColorOfSquareByHits(hits) {
            let color = this.colorScale[1]
            if (hits === 2) {
                return this.colorScale[2]
            } else if (hits === 3) {
                return this.colorScale[3]
            } else if (hits === 4) {
                return this.colorScale[4]
            } else if (hits === 5) {
                return this.colorScale[5]
            } else if (hits === 6) {
                return this.colorScale[6]
            }
            // if (hits >= 50) {
            //     return this.colorScale[6]
            // } else if (hits > 20) {
            //     return this.colorScale[5]
            // } else if (hits > 10) {
            //     return this.colorScale[4]
            // } else if (hits > 5) {
            //     return this.colorScale[3]
            // } else if (hits > 2) {
            //     return this.colorScale[2]
            // }

            return color
        },
        loadTrackFromLocalStorage(trackName) {
            // this track can be added to Local Storage by visiting the home page of the app,
            // uploading a track (export from Strava for example) and in method 'handleTrackImported'
            // you uncomment the coarseTrack lines (start of method).
            const trackRaw = localStorage.getItem(trackName).split(', ');
            trackRaw.pop()
            const track = []
            trackRaw.forEach((item, index, arr) => {
                if (index % 2 === 1) {
                    track.push([parseFloat(arr[index-1].slice(1)), parseFloat(item.slice(0, -1))])
                }
            })
            return track
        },
    },
    mounted() {
        this.initMap()
    },
}
</script>

<style lang="less" scoped>
#mapid {
    height: 100vh;
}
</style>