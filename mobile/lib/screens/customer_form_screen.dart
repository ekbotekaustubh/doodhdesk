import 'package:flutter/material.dart';
import '../core/api/api_client.dart';
import '../core/constants/api_constants.dart';
import '../models/customer.dart';

class CustomerFormScreen extends StatefulWidget {
  final Customer? customer;
  const CustomerFormScreen({super.key, this.customer});

  @override
  State<CustomerFormScreen> createState() => _CustomerFormScreenState();
}

class _CustomerFormScreenState extends State<CustomerFormScreen> {
  final ApiClient _api = ApiClient();

  final _nameController = TextEditingController();
  final _phoneController = TextEditingController();
  final _addressController = TextEditingController();
  final _routeController = TextEditingController(text: 'Main Route');

  double _cowQty = 1.0;
  double? _cowRate;
  double _bufQty = 0.0;
  double? _bufRate;

  bool _isLoading = false;
  String? _errorMessage;

  @override
  void initState() {
    super.initState();
    if (widget.customer != null) {
      final c = widget.customer!;
      _nameController.text = c.name;
      _phoneController.text = c.phone;
      _addressController.text = c.address;
      _routeController.text = c.routeName;

      for (var s in c.subscriptions) {
        if (s.productCode == 'cow') {
          _cowQty = s.defaultQuantity;
          _cowRate = s.customRate;
        } else if (s.productCode == 'buffalo') {
          _bufQty = s.defaultQuantity;
          _bufRate = s.customRate;
        }
      }
    }
  }

  Future<void> _saveCustomer() async {
    final name = _nameController.text.trim();
    final phone = _phoneController.text.trim();
    final address = _addressController.text.trim();
    final route = _routeController.text.trim();

    if (name.isEmpty || phone.isEmpty) {
      setState(() => _errorMessage = 'Name and Phone Number are required.');
      return;
    }

    setState(() {
      _isLoading = true;
      _errorMessage = null;
    });

    final List<Map<String, dynamic>> subs = [];
    if (_cowQty > 0) {
      subs.add({'product_id': 1, 'quantity': _cowQty, 'custom_rate': _cowRate, 'shift': 'both'});
    }
    if (_bufQty > 0) {
      subs.add({'product_id': 2, 'quantity': _bufQty, 'custom_rate': _bufRate, 'shift': 'both'});
    }

    final payload = {
      'name': name,
      'phone': phone,
      'address': address,
      'route_name': route.isEmpty ? 'Main Route' : route,
      'status': 'active',
      'subscriptions': subs,
    };

    ApiResponse res;
    if (widget.customer == null) {
      res = await _api.post(ApiConstants.customers, data: payload);
    } else {
      res = await _api.put('${ApiConstants.customers}/${widget.customer!.id}', data: payload);
    }

    setState(() => _isLoading = false);

    if (res.success || res.statusCode == 200 || res.statusCode == 201) {
      if (mounted) Navigator.pop(context, true);
    } else {
      setState(() {
        _errorMessage = res.error ?? 'Failed to save customer.';
      });
    }
  }

  @override
  Widget build(BuildContext context) {
    final isEdit = widget.customer != null;

    return Scaffold(
      appBar: AppBar(
        title: Text(isEdit ? 'Edit Customer' : 'Add New Customer'),
      ),
      body: SingleChildScrollView(
        padding: const EdgeInsets.all(20),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            if (_errorMessage != null) ...[
              Container(
                padding: const EdgeInsets.all(12),
                decoration: BoxDecoration(
                  color: const Color(0xFFFEE2E2),
                  borderRadius: BorderRadius.circular(10),
                  border: Border.all(color: const Color(0xFFFCA5A5)),
                ),
                child: Text(
                  _errorMessage!,
                  style: const TextStyle(color: Color(0xFF991B1B), fontWeight: FontWeight.bold, fontSize: 13),
                ),
              ),
              const SizedBox(height: 16),
            ],

            TextField(
              controller: _nameController,
              decoration: const InputDecoration(
                labelText: 'Customer Full Name *',
                prefixIcon: Icon(Icons.person_outline),
                border: OutlineInputBorder(),
              ),
            ),
            const SizedBox(height: 14),

            TextField(
              controller: _phoneController,
              keyboardType: TextInputType.phone,
              decoration: const InputDecoration(
                labelText: 'Phone Number (for WhatsApp bills) *',
                prefixIcon: Icon(Icons.phone_outlined),
                border: OutlineInputBorder(),
              ),
            ),
            const SizedBox(height: 14),

            TextField(
              controller: _addressController,
              decoration: const InputDecoration(
                labelText: 'Delivery Address (House/Flat No)',
                prefixIcon: Icon(Icons.location_on_outlined),
                border: OutlineInputBorder(),
              ),
            ),
            const SizedBox(height: 14),

            TextField(
              controller: _routeController,
              decoration: const InputDecoration(
                labelText: 'Route Name',
                prefixIcon: Icon(Icons.alt_route),
                border: OutlineInputBorder(),
              ),
            ),
            const SizedBox(height: 24),

            const Text(
              'Daily Milk Subscriptions',
              style: TextStyle(fontSize: 16, fontWeight: FontWeight.w900),
            ),
            const SizedBox(height: 12),

            // Cow Milk Card
            Card(
              child: Padding(
                padding: const EdgeInsets.all(12),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    const Text('🐄 Fresh Cow Milk', style: TextStyle(fontWeight: FontWeight.bold, fontSize: 14)),
                    const SizedBox(height: 8),
                    Row(
                      children: [
                        Expanded(
                          child: DropdownButtonFormField<double>(
                            value: _cowQty,
                            decoration: const InputDecoration(labelText: 'Daily Qty', border: OutlineInputBorder()),
                            items: [0.0, 0.5, 1.0, 1.5, 2.0, 2.5, 3.0, 4.0, 5.0]
                                .map((q) => DropdownMenuItem(value: q, child: Text('$q L')))
                                .toList(),
                            onChanged: (val) => setState(() => _cowQty = val ?? 1.0),
                          ),
                        ),
                        const SizedBox(width: 12),
                        Expanded(
                          child: TextFormField(
                            initialValue: _cowRate?.toString() ?? '',
                            keyboardType: TextInputType.number,
                            decoration: const InputDecoration(
                              labelText: 'Rate (₹/L)',
                              hintText: 'Default: 60.00',
                              border: OutlineInputBorder(),
                            ),
                            onChanged: (val) => _cowRate = double.tryParse(val),
                          ),
                        ),
                      ],
                    ),
                  ],
                ),
              ),
            ),
            const SizedBox(height: 10),

            // Buffalo Milk Card
            Card(
              child: Padding(
                padding: const EdgeInsets.all(12),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    const Text('🐃 Fresh Buffalo Milk', style: TextStyle(fontWeight: FontWeight.bold, fontSize: 14)),
                    const SizedBox(height: 8),
                    Row(
                      children: [
                        Expanded(
                          child: DropdownButtonFormField<double>(
                            value: _bufQty,
                            decoration: const InputDecoration(labelText: 'Daily Qty', border: OutlineInputBorder()),
                            items: [0.0, 0.5, 1.0, 1.5, 2.0, 2.5, 3.0, 4.0, 5.0]
                                .map((q) => DropdownMenuItem(value: q, child: Text('$q L')))
                                .toList(),
                            onChanged: (val) => setState(() => _bufQty = val ?? 0.0),
                          ),
                        ),
                        const SizedBox(width: 12),
                        Expanded(
                          child: TextFormField(
                            initialValue: _bufRate?.toString() ?? '',
                            keyboardType: TextInputType.number,
                            decoration: const InputDecoration(
                              labelText: 'Rate (₹/L)',
                              hintText: 'Default: 70.00',
                              border: OutlineInputBorder(),
                            ),
                            onChanged: (val) => _bufRate = double.tryParse(val),
                          ),
                        ),
                      ],
                    ),
                  ],
                ),
              ),
            ),
            const SizedBox(height: 28),

            ElevatedButton(
              onPressed: _isLoading ? null : _saveCustomer,
              style: ElevatedButton.styleFrom(
                backgroundColor: const Color(0xFF0284C7),
                padding: const EdgeInsets.symmetric(vertical: 14),
              ),
              child: _isLoading
                  ? const SizedBox(width: 20, height: 20, child: CircularProgressIndicator(color: Colors.white, strokeWidth: 2))
                  : Text(isEdit ? 'Save Changes' : 'Create Customer', style: const TextStyle(fontWeight: FontWeight.bold, color: Colors.white)),
            ),
          ],
        ),
      ),
    );
  }
}
