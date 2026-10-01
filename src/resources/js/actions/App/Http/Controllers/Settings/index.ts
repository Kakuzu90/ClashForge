import ProfileController from './ProfileController'
import PrivacyController from './PrivacyController'
import SecurityController from './SecurityController'
import EmailChangeController from './EmailChangeController'

const Settings = {
    ProfileController: Object.assign(ProfileController, ProfileController),
    PrivacyController: Object.assign(PrivacyController, PrivacyController),
    SecurityController: Object.assign(SecurityController, SecurityController),
    EmailChangeController: Object.assign(EmailChangeController, EmailChangeController),
}

export default Settings