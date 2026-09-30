import Home from './Home'
import Dev from './Dev'
import Upload from './Upload'
import Web from './Web'

const Controllers = {
    Home: Object.assign(Home, Home),
    Dev: Object.assign(Dev, Dev),
    Upload: Object.assign(Upload, Upload),
    Web: Object.assign(Web, Web),
}

export default Controllers