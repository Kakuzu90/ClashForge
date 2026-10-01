import ProfileController from './ProfileController'
import PrivacyController from './PrivacyController'

const Settings = {
    ProfileController: Object.assign(ProfileController, ProfileController),
    PrivacyController: Object.assign(PrivacyController, PrivacyController),
}

export default Settings