<template>
    <div>
        <h1 class="font-medium leading-tight text-5xl m-2 text-green-600">Welcome little explorer</h1>

        <div id="mapid"></div>
    </div>
</template>

<script>
export default {
    name: 'LittleExplorer',
    components: {},
    data() {
        return {
            home: [50.99408, 5.85511],
            zoomLevel: 14,
            accessToken: 'pk.eyJ1IjoicGltaG9vZ2hpZW1zdHJhIiwiYSI6ImNrbnZ1cnRjZDA5Yngyd3Bta3Y2NXMydm0ifQ.eMPCdzzcSvMwIXRgRn3b3Q',
            mapboxStyleId: 'ckpzbydzn1d0r17k8ci4bxyid',
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
        },
    },
    mounted() {
        this.initMap()
    },
}
</script>

<style lang="less" scoped>
#mapid {
    height: 74vh;
}
</style>