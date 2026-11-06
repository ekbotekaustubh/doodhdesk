import 'package:flutter/material.dart';
import '../core/api/api_client.dart';
import '../core/constants/api_constants.dart';
import '../models/invoice.dart';
import 'invoice_detail_screen.dart';

class InvoiceListScreen extends StatefulWidget {
  const InvoiceListScreen({super.key});

  @override
  State<InvoiceListScreen> createState() => _InvoiceListScreenState();
}

class _InvoiceListScreenState extends State<InvoiceListScreen> {
  final ApiClient _api = ApiClient();

  int _selectedMonth = DateTime.now().month;
  int _selectedYear = DateTime.now().year;

  List<Invoice> _invoices = [];
  bool _isLoading = false;

  @override
  void initState() {
    super.initState();
    _loadInvoices();
  }

  Future<void> _loadInvoices() async {
    setState(() => _isLoading = true);

    final res = await _api.get(ApiConstants.invoices, queryParameters: {
      'month': _selectedMonth,
      'year': _selectedYear,
    });

    if (res.success && res.data != null) {
      final list = (res.data as List).map((m) => Invoice.fromJson(m)).toList();
      setState(() => _invoices = list);
    }

    setState(() => _isLoading = false);
  }

  Future<void> _generateBatch() async {
    final confirm = await showDialog<bool>(
      context: context,
      builder: (ctx) => AlertDialog(
        title: const Text('Batch Generate Invoices'),
        content: Text('Calculate milk bills for all customers for $_selectedMonth/$_selectedYear?'),
        actions: [
          TextButton(onPressed: () => Navigator.pop(ctx, false), child: const Text('Cancel')),
          ElevatedButton(onPressed: () => Navigator.pop(ctx, true), child: const Text('Generate All')),
        ],
      ),
    );

    if (confirm != true) return;

    setState(() => _isLoading = true);
    final res = await _api.post(ApiConstants.invoiceBatch, data: {
      'month': _selectedMonth,
      'year': _selectedYear,
    });

    if (res.success) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Batch invoices generated successfully!')),
      );
      _loadInvoices();
    } else {
      setState(() => _isLoading = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final totalBilled = _invoices.fold(0.0, (sum, i) => sum + i.finalAmount);
    final totalPending = _invoices.fold(0.0, (sum, i) => sum + i.balanceDue);

    return Scaffold(
      appBar: AppBar(
        title: const Text('🧾 Monthly Billing & Invoices'),
        actions: [
          IconButton(
            icon: const Icon(Icons.bolt),
            tooltip: 'Batch Generate Invoices',
            onPressed: _generateBatch,
          ),
        ],
      ),
      body: Column(
        children: [
          // Month Selector Bar
          Container(
            padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 12),
            color: Colors.white,
            child: Row(
              mainAxisAlignment: MainAxisAlignment.spaceBetween,
              children: [
                DropdownButton<int>(
                  value: _selectedMonth,
                  items: List.generate(12, (i) => i + 1).map((m) {
                    return DropdownMenuItem(value: m, child: Text('Month: $m'));
                  }).toList(),
                  onChanged: (val) {
                    if (val != null) {
                      setState(() => _selectedMonth = val);
                      _loadInvoices();
                    }
                  },
                ),
                DropdownButton<int>(
                  value: _selectedYear,
                  items: [2025, 2026, 2027].map((y) {
                    return DropdownMenuItem(value: y, child: Text('$y'));
                  }).toList(),
                  onChanged: (val) {
                    if (val != null) {
                      setState(() => _selectedYear = val);
                      _loadInvoices();
                    }
                  },
                ),
                ElevatedButton.icon(
                  onPressed: _generateBatch,
                  icon: const Icon(Icons.play_arrow, size: 16),
                  label: const Text('Generate'),
                  style: ElevatedButton.styleFrom(
                    minimumSize: const Size(100, 36),
                    padding: const EdgeInsets.symmetric(horizontal: 10),
                  ),
                ),
              ],
            ),
          ),

          // Financial Summary Card
          Container(
            padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 10),
            color: const Color(0xFFF1F5F9),
            child: Row(
              mainAxisAlignment: MainAxisAlignment.spaceBetween,
              children: [
                Text(
                  'Total Billed: ₹${totalBilled.toStringAsFixed(2)}',
                  style: const TextStyle(fontSize: 12, fontWeight: FontWeight.bold, color: Color(0xFF334155)),
                ),
                Text(
                  'Pending: ₹${totalPending.toStringAsFixed(2)}',
                  style: const TextStyle(fontSize: 12, fontWeight: FontWeight.w900, color: Color(0xFFDC2626)),
                ),
              ],
            ),
          ),

          // Invoices List
          Expanded(
            child: _isLoading
                ? const Center(child: CircularProgressIndicator())
                : _invoices.isEmpty
                    ? Center(
                        child: Column(
                          mainAxisAlignment: MainAxisAlignment.center,
                          children: [
                            const Text('🧾', style: TextStyle(fontSize: 40)),
                            const SizedBox(height: 8),
                            const Text('No invoices generated for this month'),
                            const SizedBox(height: 12),
                            ElevatedButton(
                              onPressed: _generateBatch,
                              style: ElevatedButton.styleFrom(minimumSize: const Size(160, 40)),
                              child: const Text('Generate Now'),
                            ),
                          ],
                        ),
                      )
                    : RefreshIndicator(
                        onRefresh: _loadInvoices,
                        child: ListView.builder(
                          padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 6),
                          itemCount: _invoices.length,
                          itemBuilder: (context, idx) {
                            final inv = _invoices[idx];
                            final isPaid = inv.paymentStatus == 'paid';

                            return Card(
                              margin: const EdgeInsets.symmetric(vertical: 5),
                              child: ListTile(
                                onTap: () async {
                                  final changed = await Navigator.push(
                                    context,
                                    MaterialPageRoute(builder: (_) => InvoiceDetailScreen(invoiceId: inv.id)),
                                  );
                                  if (changed == true) _loadInvoices();
                                },
                                title: Text(inv.customerName, style: const TextStyle(fontWeight: FontWeight.bold)),
                                subtitle: Text('${inv.totalUnits.toStringAsFixed(1)} Units • Phone: ${inv.customerPhone}'),
                                trailing: Column(
                                  mainAxisAlignment: MainAxisAlignment.center,
                                  crossAxisAlignment: CrossAxisAlignment.end,
                                  children: [
                                    Text(
                                      '₹${inv.finalAmount.toStringAsFixed(2)}',
                                      style: const TextStyle(fontWeight: FontWeight.w900, fontSize: 15),
                                    ),
                                    Container(
                                      padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 2),
                                      decoration: BoxDecoration(
                                        color: isPaid ? const Color(0xFFDCFCE7) : const Color(0xFFFEE2E2),
                                        borderRadius: BorderRadius.circular(6),
                                      ),
                                      child: Text(
                                        isPaid ? 'PAID' : 'DUE ₹${inv.balanceDue.toStringAsFixed(0)}',
                                        style: TextStyle(
                                          fontSize: 10,
                                          fontWeight: FontWeight.bold,
                                          color: isPaid ? const Color(0xFF15803D) : const Color(0xFFB91C1C),
                                        ),
                                      ),
                                    ),
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
