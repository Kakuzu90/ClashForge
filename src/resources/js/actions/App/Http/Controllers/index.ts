import Home from './Home'
import Dev from './Dev'
import Upload from './Upload'

const Controllers = {
    Home: Object.assign(Home, Home),
    Dev: Object.assign(Dev, Dev),
    Upload: Object.assign(Upload, Upload),
}

export default Controllers