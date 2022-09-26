<template>
    <div>
        <h1 class="font-medium leading-tight text-5xl m-2 text-green-600">Welcome little explorer</h1>

        <div id="mapid" class="border-2 border-green-600"></div>
    </div>
</template>

<script>
import {calculatePointToPointDistance as p2pDistance} from '../libs/distance'

export default {
    name: 'LittleExplorer',
    components: {},
    data() {
        return {
            home: [50.99408, 5.85511],
            zoomLevel: 14,
            accessToken: 'pk.eyJ1IjoicGltaG9vZ2hpZW1zdHJhIiwiYSI6ImNrbnZ1cnRjZDA5Yngyd3Bta3Y2NXMydm0ifQ.eMPCdzzcSvMwIXRgRn3b3Q',
            mapboxStyleId: 'ckpzbydzn1d0r17k8ci4bxyid',
            latInterval: 0.0009,
            lngInterval: 0.00143,
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

            // Our home
            L.marker(this.home).addTo(this.mymap);

            // The grid
            const gridItems = this.createGridItems(this.home, this.latInterval, this.lngInterval, 60)
            L.featureGroup(gridItems).addTo(this.mymap)

            // Track (coarsened)
            const trackRaw = localStorage.getItem('track').split(', ');
            trackRaw.pop()
            const track = []
            trackRaw.forEach((item, index, arr) => {
                if (index % 2 === 1) {
                    track.push([parseFloat(arr[index-1].slice(1)), parseFloat(item.slice(0, -1))])
                }
            })
            L.polygon(track, {color: '#bbb', fillColor: '#eee'}).addTo(this.mymap);

        },
        createGridItems(centerPoint, deltaLat, deltaLng, nPoints) {
            const grid = []
            for (let i = 0; i < nPoints; i++) {
                const topLat = (-nPoints * deltaLat / 2) + this.home[0] + deltaLat * (i + 1)
                const bottomLat = (-nPoints * deltaLat / 2) + this.home[0] + deltaLat * i 
                for (let j = 0; j < nPoints; j++) {
                    const leftLng = (-nPoints * deltaLng / 2) + this.home[1] + deltaLng * j 
                    const rightLng = (-nPoints * deltaLng / 2) + this.home[1] + deltaLng * (j + 1)

                    grid.push(L.rectangle([[topLat, leftLng], [bottomLat, rightLng]], {
                        color: '#699669',
                        weight: 1,
                    }))
                }
            }

            return grid
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