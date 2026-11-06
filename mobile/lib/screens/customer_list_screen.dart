import 'package:flutter/material.dart';
import '../core/api/api_client.dart';
import '../core/constants/api_constants.dart';
import '../models/customer.dart';
import 'customer_form_screen.dart';
import 'customer_leave_screen.dart';

class CustomerListScreen extends StatefulWidget {
  const CustomerListScreen({super.key});

  @override
  State<CustomerListScreen> createState() => _CustomerListScreenState();
}

class _CustomerListScreenState extends State<CustomerListScreen> {
  final ApiClient _api = ApiClient();
  final TextEditingController _searchController = TextEditingController();

  List<Customer> _customers = [];
  Map<String, dynamic>? _quota;
  bool _isLoading = false;

  @override
  void initState() {
    super.initState();
    _loadCustomers();
  }

  Future<void> _loadCustomers() async {
    setState(() => _isLoading = true);

    final res = await _api.get(ApiConstants.customers, queryParameters: {
      'q': _searchController.text.trim(),
    });

    if (res.success && res.data != null) {
      final list = (res.data as List).map((m) => Customer.fromJson(m)).toList();
      setState(() {
        _customers = list;
        _quota = res.meta?['quota'];
      });
    }

    setState(() => _isLoading = false);
  }

  @override
  Widget build(BuildContext context) {
    final activeCount = _quota?['active_customers'] ?? _customers.where((c) => c.status == 'active').length;
    final maxAllowed = _quota?['max_allowed'] ?? 5;

    return Scaffold(
      appBar: AppBar(
        title: const Text('👥 Customers & Roster'),
        actions: [
          IconButton(
            icon: const Icon(Icons.add),
            tooltip: 'Add Customer',
            onPressed: () async {
              final added = await Navigator.push(
                context,
                MaterialPageRoute(builder: (_) => const CustomerFormScreen()),
              );
              if (added == true) _loadCustomers();
            },
          ),
        ],
      ),
      body: Column(
        children: [
          // Quota Usage Banner
          Container(
            padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 10),
            color: const Color(0xFFEFF6FF),
            child: Row(
              mainAxisAlignment: MainAxisAlignment.spaceBetween,
              children: [
                Text(
                  'Plan Quota: $activeCount / $maxAllowed Customers',
                  style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 13, color: Color(0xFF1E40AF)),
                ),
                Text(
                  '${maxAllowed - activeCount} free slots',
                  style: const TextStyle(fontSize: 12, fontWeight: FontWeight.bold, color: Color(0xFF2563EB)),
                ),
              ],
            ),
          ),

          // Search Bar
          Padding(
            padding: const EdgeInsets.all(12),
            child: TextField(
              controller: _searchController,
              onChanged: (_) => _loadCustomers(),
              decoration: InputDecoration(
                prefixIcon: const Icon(Icons.search, size: 20),
                hintText: 'Search by name, phone, or route...',
                border: OutlineInputBorder(borderRadius: BorderRadius.circular(12)),
                contentPadding: const EdgeInsets.symmetric(horizontal: 14, vertical: 10),
              ),
            ),
          ),

          // Customer Cards
          Expanded(
            child: _isLoading
                ? const Center(child: CircularProgressIndicator())
                : _customers.isEmpty
                    ? const Center(child: Text('No customers found'))
                    : RefreshIndicator(
                        onRefresh: _loadCustomers,
                        child: ListView.builder(
                          padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 6),
                          itemCount: _customers.length,
                          itemBuilder: (context, idx) {
                            final c = _customers[idx];
                            return Card(
                              margin: const EdgeInsets.symmetric(vertical: 5),
                              child: ListTile(
                                leading: CircleAvatar(
                                  backgroundColor: c.status == 'active' ? const Color(0xFF0284C7) : Colors.grey,
                                  foregroundColor: Colors.white,
                                  child: Text(c.name.isNotEmpty ? c.name[0].toUpperCase() : 'C'),
                                ),
                                title: Text(
                                  c.name,
                                  style: const TextStyle(fontWeight: FontWeight.bold),
                                ),
                                subtitle: Column(
                                  crossAxisAlignment: CrossAxisAlignment.start,
                                  children: [
                                    Text('${c.phone} • ${c.routeName}'),
                                    const SizedBox(height: 2),
                                    Text(
                                      'Daily: ${c.totalDailyQuantity.toStringAsFixed(1)}L (${c.milkTypes})',
                                      style: const TextStyle(fontSize: 11, fontWeight: FontWeight.bold, color: Color(0xFF0284C7)),
                                    ),
                                  ],
                                ),
                                trailing: PopupMenuButton<String>(
                                  onSelected: (val) async {
                                    if (val == 'edit') {
                                      final updated = await Navigator.push(
                                        context,
                                        MaterialPageRoute(builder: (_) => CustomerFormScreen(customer: c)),
                                      );
                                      if (updated == true) _loadCustomers();
                                    } else if (val == 'vacation') {
                                      Navigator.push(
                                        context,
                                        MaterialPageRoute(builder: (_) => CustomerLeaveScreen(customerId: c.id, customerName: c.name)),
                                      );
                                    }
                                  },
                                  itemBuilder: (_) => [
                                    const PopupMenuItem(value: 'edit', child: Text('✏️ Edit Profile')),
                                    const PopupMenuItem(value: 'vacation', child: Text('🏖 Vacation / Pause')),
                                  ],
                                ),
                              ),
                            );
                          },
                        ),
                      ),
          ),
        ],
      ),
    );
  }
}
