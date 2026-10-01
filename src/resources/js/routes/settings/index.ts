import notifications from './notifications'
import profile from './profile'
import privacy from './privacy'
import security from './security'
import dangerZone from './danger-zone'
import email from './email'

const settings = {
    notifications: Object.assign(notifications, notifications),
    profile: Object.assign(profile, profile),
    privacy: Object.assign(privacy, privacy),
    security: Object.assign(security, security),
    dangerZone: Object.assign(dangerZone, dangerZone),
    email: Object.assign(email, email),
}

export default settings