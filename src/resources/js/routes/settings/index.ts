import profile from './profile'
import privacy from './privacy'
import security from './security'
import email from './email'

const settings = {
    profile: Object.assign(profile, profile),
    privacy: Object.assign(privacy, privacy),
    security: Object.assign(security, security),
    email: Object.assign(email, email),
}

export default settings