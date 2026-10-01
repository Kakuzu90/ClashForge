import Home from './Home'
import Dev from './Dev'
import Auth from './Auth'
import Account from './Account'
import Profile from './Profile'
import Settings from './Settings'
import Notifications from './Notifications'
import Upload from './Upload'
import Admin from './Admin'
import Web from './Web'

const Controllers = {
    Home: Object.assign(Home, Home),
    Dev: Object.assign(Dev, Dev),
    Auth: Object.assign(Auth, Auth),
    Account: Object.assign(Account, Account),
    Profile: Object.assign(Profile, Profile),
    Settings: Object.assign(Settings, Settings),
    Notifications: Object.assign(Notifications, Notifications),
    Upload: Object.assign(Upload, Upload),
    Admin: Object.assign(Admin, Admin),
    Web: Object.assign(Web, Web),
}

export default Controllers