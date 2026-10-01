import DashboardController from './DashboardController'
import AuditLogController from './AuditLogController'

const Admin = {
    DashboardController: Object.assign(DashboardController, DashboardController),
    AuditLogController: Object.assign(AuditLogController, AuditLogController),
}

export default Admin