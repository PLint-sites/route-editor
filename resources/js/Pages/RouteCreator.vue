<template>
    <div id="mapid"></div>
</template>

<script>
export default {
    name: 'RouteCreator',
    props: ['track', 'distance'],
    data() {
        return {
            mymap: null,
            accessToken: 'pk.eyJ1IjoicGltaG9vZ2hpZW1zdHJhIiwiYSI6ImNrbnZ1cnRjZDA5Yngyd3Bta3Y2NXMydm0ifQ.eMPCdzzcSvMwIXRgRn3b3Q',
            home: [51.01097800768912, 5.856136009097099],
        }
    },
    methods: {
        initMap() {
            console.log('init map')
            this.mymap = L.map('mapid').setView(this.home, 16)
            L.tileLayer(`https://api.mapbox.com/styles/v1/{id}/tiles/{z}/{x}/{y}?access_token=${this.accessToken}`, {
                attribution: 'Map data &copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors, Imagery © <a href="https://www.mapbox.com/">Mapbox</a>',
                maxZoom: 18,
                id: 'mapbox/streets-v11',
                tileSize: 512,
                zoomOffset: -1,
                accessToken: this.accessToken
            }).addTo(this.mymap);
        },
    },
    mounted() {
        this.initMap()
    },
}
</script>

<style scoped>
#mapid {
    height: 80vh;
}
</style>