import 'package:flutter/material.dart';
import '../core/api/api_client.dart';
import '../core/api/auth_service.dart';
import '../core/constants/api_constants.dart';
import 'login_screen.dart';

class DashboardScreen extends StatefulWidget {
  const DashboardScreen({super.key});

  @override
  State<DashboardScreen> createState() => _DashboardScreenState();
}

class _DashboardScreenState extends State<DashboardScreen> {
  final ApiClient _api = ApiClient();
  final AuthService _authService = AuthService();

  Map<String, dynamic>? _stats;
  Map<String, dynamic>? _quota;
  Map<String, dynamic>? _user;
  bool _isLoading = false;

  @override
  void initState() {
    super.initState();
    _loadDashboard();
  }

  Future<void> _loadDashboard() async {
    setState(() => _isLoading = true);

    final res = await _api.get(ApiConstants.dashboardStats);
    final user = await _authService.getSavedUser();

    if (res.success && res.data != null) {
      setState(() {
        _stats = res.data;
        _quota = res.data['quota'];
        _user = user;
      });
    }

    setState(() => _isLoading = false);
  }

  Future<void> _handleLogout() async {
    final confirm = await showDialog<bool>(
      context: context,
      builder: (ctx) => AlertDialog(
        title: const Text('Confirm Logout'),
        content: const Text('Are you sure you want to sign out?'),
        actions: [
          TextButton(onPressed: () => Navigator.pop(ctx, false), child: const Text('Cancel')),
          ElevatedButton(onPressed: () => Navigator.pop(ctx, true), child: const Text('Sign Out')),
        ],
      ),
    );

    if (confirm == true) {
      await _authService.logout();
      if (!mounted) return;
      Navigator.pushAndRemoveUntil(
        context,
        MaterialPageRoute(builder: (_) => const LoginScreen()),
        (route) => false,
      );
    }
  }

  @override
  Widget build(BuildContext context) {
    if (_isLoading) {
      return Scaffold(
        appBar: AppBar(title: const Text('⚙️ Dashboard & Settings')),
        body: const Center(child: CircularProgressIndicator()),
      );
    }

    final morningStats = _stats?['shifts']?['morning'] ?? {};
    final eveningStats = _stats?['shifts']?['evening'] ?? {};
    final billing = _stats?['billing_month'] ?? {};

    final activeCount = _quota?['active_customers'] ?? 0;
    final maxAllowed = _quota?['max_allowed'] ?? 5;

    return Scaffold(
      appBar: AppBar(
        title: const Text('⚙️ Dashboard & Settings'),
        actions: [
          IconButton(
            icon: const Icon(Icons.logout),
            tooltip: 'Sign Out',
            onPressed: _handleLogout,
          ),
        ],
      ),
      body: RefreshIndicator(
        onRefresh: _loadDashboard,
        child: SingleChildScrollView(
          padding: const EdgeInsets.all(16),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              // Dairy Profile Header
              Card(
                child: Padding(
                  padding: const EdgeInsets.all(16),
                  child: Row(
                    children: [
                      const CircleAvatar(
                        radius: 28,
                        backgroundColor: Color(0xFF0284C7),
                        child: Text('🥛', style: TextStyle(fontSize: 28)),
                      ),
                      const SizedBox(width: 14),
                      Expanded(
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Text(
                              _user?['business_name'] ?? 'Krushna Dairy Farm',
                              style: const TextStyle(fontSize: 18, fontWeight: FontWeight.w900),
                            ),
                            const SizedBox(height: 2),
                            Text(
                              'Owner: ${_user?['owner_name'] ?? 'Milkman'} • ${_user?['phone'] ?? ''}',
                              style: const TextStyle(fontSize: 12, color: Colors.black54),
                            ),
                          ],
                        ),
                      ),
                    ],
                  ),
                ),
              ),
              const SizedBox(height: 16),

              // Quota Card
              Card(
                color: const Color(0xFFEFF6FF),
                child: Padding(
                  padding: const EdgeInsets.all(16),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Row(
                        mainAxisAlignment: MainAxisAlignment.between,
                        children: [
                          const Text('⚡ Customer Quota Limit', style: TextStyle(fontWeight: FontWeight.bold, fontSize: 14, color: Color(0xFF1E40AF))),
                          Text('$activeCount / $maxAllowed slots', style: const TextStyle(fontWeight: FontWeight.w900, color: Color(0xFF0284C7))),
                        ],
                      ),
                      const SizedBox(height: 8),
                      LinearProgressIndicator(
                        value: maxAllowed > 0 ? (activeCount / maxAllowed).clamp(0.0, 1.0) : 0.0,
                        backgroundColor: Colors.blue.shade100,
                        color: const Color(0xFF0284C7),
                        minHeight: 8,
                        borderRadius: BorderRadius.circular(4),
                      ),
                      const SizedBox(height: 8),
                      Text(
                        'Tier: ${_quota?['payment_status'] == 'active' ? 'Paid Extended Tier' : 'Free 5-Customer Tier'}',
                        style: const TextStyle(fontSize: 11, color: Color(0xFF1E40AF)),
                      ),
                    ],
                  ),
                ),
              ),
              const SizedBox(height: 16),

              // Shift Volumes Grid
              const Text('Today Delivery Volumes:', style: TextStyle(fontWeight: FontWeight.bold, fontSize: 14)),
              const SizedBox(height: 8),
              Row(
                children: [
                  Expanded(
                    child: Card(
                      child: Padding(
                        padding: const EdgeInsets.all(14),
                        child: Column(
                          children: [
                            const Text('🌅 Morning Shift', style: TextStyle(fontWeight: FontWeight.bold, fontSize: 13, color: Color(0xFFD97706))),
                            const SizedBox(height: 6),
                            Text('${(morningStats['total_liters'] as num?)?.toDouble() ?? 0.0} L', style: const TextStyle(fontSize: 22, fontWeight: FontWeight.w900)),
                            Text('${morningStats['delivered'] ?? 0} houses delivered', style: const TextStyle(fontSize: 10, color: Colors.black54)),
                          ],
                        ),
                      ),
                    ),
                  ),
                  const SizedBox(width: 10),
                  Expanded(
                    child: Card(
                      child: Padding(
                        padding: const EdgeInsets.all(14),
                        child: Column(
                          children: [
                            const Text('🌆 Evening Shift', style: TextStyle(fontWeight: FontWeight.bold, fontSize: 13, color: Color(0xFF4F46E5))),
                            const SizedBox(height: 6),
                            Text('${(eveningStats['total_liters'] as num?)?.toDouble() ?? 0.0} L', style: const TextStyle(fontSize: 22, fontWeight: FontWeight.w900)),
                            Text('${eveningStats['delivered'] ?? 0} houses delivered', style: const TextStyle(fontSize: 10, color: Colors.black54)),
                          ],
                        ),
                      ),
                    ),
                  ),
                ],
              ),
              const SizedBox(height: 16),

              // Month Financials Card
              Card(
                child: Padding(
                  padding: const EdgeInsets.all(16),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      const Text('Month-to-Date Financials', style: TextStyle(fontWeight: FontWeight.bold, fontSize: 14)),
                      const SizedBox(height: 12),
                      Row(
                        mainAxisAlignment: MainAxisAlignment.spaceBetween,
                        children: [
                          const Text('Total Billed:'),
                          Text('₹${(billing['total_billed'] as num?)?.toDouble().toStringAsFixed(2) ?? '0.00'}', style: const TextStyle(fontWeight: FontWeight.bold)),
                        ],
                      ),
                      const SizedBox(height: 6),
                      Row(
                        mainAxisAlignment: MainAxisAlignment.spaceBetween,
                        children: [
                          const Text('Total Collected:'),
                          Text('₹${(billing['total_collected'] as num?)?.toDouble().toStringAsFixed(2) ?? '0.00'}', style: const TextStyle(fontWeight: FontWeight.bold, color: Color(0xFF16A34A))),
                        ],
                      ),
                      const SizedBox(height: 6),
                      Row(
                        mainAxisAlignment: MainAxisAlignment.spaceBetween,
                        children: [
                          const Text('Pending Balance:'),
                          Text('₹${(billing['pending_balance'] as num?)?.toDouble().toStringAsFixed(2) ?? '0.00'}', style: const TextStyle(fontWeight: FontWeight.w900, color: Color(0xFFDC2626))),
                        ],
                      ),
                    ],
                  ),
                ),
              ),
              const SizedBox(height: 24),

              OutlinedButton.icon(
                onPressed: _handleLogout,
                icon: const Icon(Icons.logout, color: Colors.red),
                label: const Text('Sign Out', style: TextStyle(color: Colors.red, fontWeight: FontWeight.bold)),
              ),
            ],
          ),
        ),
      ),
    );
  }
}
