import 'dart:io';

class ApiConstants {
  // Default development endpoints:
  // - Android Emulator uses 10.0.2.2 to reach host machine
  // - iOS Simulator uses localhost
  static String get defaultBaseUrl {
    if (Platform.isAndroid) {
      return 'http://10.0.2.2/doodhdesk/index.php/api';
    }
    return 'http://localhost/doodhdesk/index.php/api';
  }

  // Endpoints
  static const String login = '/auth/login';
  static const String register = '/auth/register';
  static const String me = '/auth/me';
  static const String logout = '/auth/logout';

  static const String dashboardStats = '/dashboard/stats';

  static const String products = '/products';

  static const String customers = '/customers';
  static const String routes = '/customers/routes';

  static const String deliveries = '/deliveries';
  static const String deliveryToggle = '/deliveries/toggle';
  static const String deliveryEditQty = '/deliveries/edit-quantity';
  static const String deliveryBulk = '/deliveries/bulk-deliver';
  static const String deliverySummary = '/deliveries/summary';

  static const String invoices = '/invoices';
  static const String invoiceBatch = '/invoices/generate-batch';
  static const String invoiceSingle = '/invoices/generate-single';

  static const String payments = '/payments';
  static const String settings = '/settings';
  static const String subscription = '/subscription';
}
