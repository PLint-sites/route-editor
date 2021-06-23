<template>
    <div id="upload-form">
        <form enctype="multipart/form-data">
            <div class="dropbox">
                <h2>Upload GPX</h2>
                <p>Drag your file(s) here to begin<br> or click to browse</p>
                <input type="file" multiple :name="fieldName" @change="filesChange($event.target.name, $event.target.files); fileCount = $event.target.files.length" class="input-file">
            </div>
        </form>
    </div>
</template>

<script>
export default {
    name: 'Dropzone',
    data() {
        return {
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
            .then(({data}) => {
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
        position: relative;
        cursor: pointer;

        &:hover {
            background: lightblue; /* when mouse over to the drop zone, change color */
        }

        .input-file {
            opacity: 0; /* invisible but it's there! */
            width: 100%;
            position: relative;
            cursor: pointer;
            height: 205px;
        }

        h2 {
            text-transform: uppercase;
            font-weight: bold;
            font-size: 1.5em;
            text-align: center;
            position: absolute;
            top: 10px;
            width: 280px;
        }

        p {
            font-size: 1.2em;
            text-align: center;
            position: absolute;
            top: 80px;
            width: 280px;
        }
    }
}
</style>