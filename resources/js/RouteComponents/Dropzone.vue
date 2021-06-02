<template>
    <div id="upload-form">
        <form enctype="multipart/form-data" v-if="isInitial || isSaving">
            <div class="dropbox">
                <input type="file" multiple :name="fieldName" :disabled="isSaving" @change="filesChange($event.target.name, $event.target.files); fileCount = $event.target.files.length" class="input-file">
                <p v-if="isInitial">
                    Drag your file(s) here to begin<br> or click to browse
                </p>
                <p v-if="isSaving">
                    Uploading {{ fileCount }} files...
                </p>
            </div>
        </form>
    </div>
</template>

<script>
export default {
    name: 'Dropzone',
    data() {
        return {
            isInitial: true,
            isSaving: false,
            fieldName: 'gpx'
        }
    },
    methods: {
        filesChange(fieldName, fileList) {
            // handle file changes
            const formData = new FormData()

            if (!fileList.length) return

            // append the files to FormData
            Array
                .from(Array(fileList.length).keys())
                .map(x => {
                    formData.append(fieldName, fileList[x], fileList[x].name)
                });

            // save it
            this.save(formData)
        },
        save(formData) {
            axios.post(`/import-gpx`, formData)
            // .then(({data: {track, distance}}) => {
            .then(({data}) => {
                // // create a new route with this data
                // this.activeRouteIndex = this.routes.length
                // this.appendRoute(route, this.colors[this.activeRouteIndex], this.activeRouteIndex, distance)
                // // create the individual points of the route
                // route.forEach(point => this.createPointOnMap(point))

                // this.showMessage(`New track imported (number of points ${track.length}), distance: ${distance}`)
                // console.log(`New track imported (number of points ${track.length}), distance: ${distance}`)
                this.$emit('track-imported', data)
            })
            .catch(error => console.log(error))
        },
    },
}
</script>

<style lang="less" scoped>
#upload-form {
    .dropbox {
        outline: 2px dashed grey; /* the dash box */
        outline-offset: -10px;
        background: lightcyan;
        color: dimgray;
        padding: 10px 10px;
        min-height: 200px; /* minimum height */
        position: relative;
        cursor: pointer;

        &:hover {
            background: lightblue; /* when mouse over to the drop zone, change color */
        }

        p {
            font-size: 1.2em;
            text-align: center;
            padding: 50px 0;
        }
    }

    .input-file {
        opacity: 0; /* invisible but it's there! */
        width: 100%;
        height: 200px;
        position: absolute;
        cursor: pointer;
    }
}
</style>