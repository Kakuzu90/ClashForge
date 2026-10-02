import DashboardController from './DashboardController'
import AuditLogController from './AuditLogController'
import UserController from './UserController'
import SystemHealthController from './SystemHealthController'
import SanctionController from './SanctionController'

const Admin = {
    DashboardController: Object.assign(DashboardController, DashboardController),
    AuditLogController: Object.assign(AuditLogController, AuditLogController),
    UserController: Object.assign(UserController, UserController),
    SystemHealthController: Object.assign(SystemHealthController, SystemHealthController),
    SanctionController: Object.assign(SanctionController, SanctionController),
}

export default Admin