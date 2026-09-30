import Home from './Home'
import Dev from './Dev'
import Auth from './Auth'
import Upload from './Upload'
import Web from './Web'

const Controllers = {
    Home: Object.assign(Home, Home),
    Dev: Object.assign(Dev, Dev),
    Auth: Object.assign(Auth, Auth),
    Upload: Object.assign(Upload, Upload),
    Web: Object.assign(Web, Web),
}

export default Controllers