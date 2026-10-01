import profile from './profile'
import privacy from './privacy'
import security from './security'

const settings = {
    profile: Object.assign(profile, profile),
    privacy: Object.assign(privacy, privacy),
    security: Object.assign(security, security),
}

export default settings