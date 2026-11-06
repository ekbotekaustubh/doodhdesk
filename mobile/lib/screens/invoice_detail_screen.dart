import 'package:flutter/material.dart';
import 'package:qr_flutter/qr_flutter.dart';
import 'package:url_launcher/url_launcher.dart';
import '../core/api/api_client.dart';
import '../core/constants/api_constants.dart';

class InvoiceDetailScreen extends StatefulWidget {
  final int invoiceId;
  const InvoiceDetailScreen({super.key, required this.invoiceId});

  @override
  State<InvoiceDetailScreen> createState() => _InvoiceDetailScreenState();
}

class _InvoiceDetailScreenState extends State<InvoiceDetailScreen> {
  final ApiClient _api = ApiClient();

  Map<String, dynamic>? _invoice;
  Map<String, dynamic>? _breakdown;
  List<dynamic> _payments = [];
  Map<String, dynamic>? _settings;
  bool _isLoading = true;

  @override
  void initState() {
    super.initState();
    _loadInvoiceDetail();
  }

  Future<void> _loadInvoiceDetail() async {
    setState(() => _isLoading = true);

    final res = await _api.get('${ApiConstants.invoices}/${widget.invoiceId}');

    if (res.success && res.data != null) {
      setState(() {
        _invoice = res.data['invoice'];
        _breakdown = res.data['breakdown'];
        _payments = res.data['payments'] ?? [];
        _settings = res.data['settings'];
      });
    }

    setState(() => _isLoading = false);
  }

  Future<void> _shareOnWhatsApp() async {
    final shareRes = await _api.get('${ApiConstants.invoices}/${widget.invoiceId}/share');
    if (shareRes.success && shareRes.data != null) {
      final waUrl = shareRes.data['whatsapp_url'];
      if (waUrl != null && waUrl.toString().isNotEmpty) {
        final uri = Uri.parse(waUrl.toString());
        if (await canLaunchUrl(uri)) {
          await launchUrl(uri, mode: LaunchMode.externalApplication);
          return;
        }
      }
    }

    ScaffoldMessenger.of(context).showSnackBar(
      const SnackBar(content: Text('Unable to launch WhatsApp')),
    );
  }

  void _showUpiQrDialog() {
    final upiId = _settings?['upi_id'] ?? 'krushnadairy@oksbi';
    final balance = (_invoice?['final_amount'] as num?)?.toDouble() ?? 0.0;
    final customerName = _invoice?['customer_name'] ?? 'Customer';

    final upiUri = 'upi://pay?pa=$upiId&pn=DoodhDesk%20Dairy&am=${balance.toStringAsFixed(2)}&cu=INR&tn=Milk%20Bill%20for%20$customerName';

    showDialog(
      context: context,
      builder: (ctx) => AlertDialog(
        title: const Text('Scan & Pay via UPI', textAlign: TextAlign.center, style: TextStyle(fontWeight: FontWeight.bold)),
        content: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Text('Amount Due: ₹${balance.toStringAsFixed(2)}', style: const TextStyle(fontSize: 18, fontWeight: FontWeight.w900, color: Color(0xFF0284C7))),
            const SizedBox(height: 16),
            SizedBox(
              width: 220,
              height: 220,
              child: QrImageView(
                data: upiUri,
                version: QrVersions.auto,
                size: 220,
              ),
            ),
            const SizedBox(height: 12),
            Text('UPI ID: $upiId', style: const TextStyle(fontSize: 12, fontWeight: FontWeight.bold, color: Colors.black54)),
            const SizedBox(height: 4),
            const Text('Scan using Google Pay, PhonePe, or Paytm', style: TextStyle(fontSize: 11, color: Colors.grey)),
          ],
        ),
        actions: [
          ElevatedButton(onPressed: () => Navigator.pop(ctx), child: const Text('Done')),
        ],
      ),
    );
  }

  void _showCollectPaymentDialog() {
    final balance = (_invoice?['final_amount'] as num?)?.toDouble() ?? 0.0;
    final amountController = TextEditingController(text: balance.toStringAsFixed(2));
    final refController = TextEditingController();
    String paymentMode = 'cash';

    showDialog(
      context: context,
      builder: (ctx) => StatefulBuilder(
        builder: (context, setModalState) => AlertDialog(
          title: const Text('Record Payment', style: TextStyle(fontWeight: FontWeight.bold)),
          content: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              TextField(
                controller: amountController,
                keyboardType: TextInputType.number,
                decoration: const InputDecoration(labelText: 'Amount Received (₹)', border: OutlineInputBorder()),
              ),
              const SizedBox(height: 12),
              DropdownButtonFormField<String>(
                value: paymentMode,
                decoration: const InputDecoration(labelText: 'Payment Mode', border: OutlineInputBorder()),
                items: const [
                  DropdownMenuItem(value: 'cash', child: Text('💵 Cash')),
                  DropdownMenuItem(value: 'upi', child: Text('📱 UPI / Google Pay')),
                  DropdownMenuItem(value: 'bank_transfer', child: Text('🏦 Bank Transfer')),
                ],
                onChanged: (val) => setModalState(() => paymentMode = val ?? 'cash'),
              ),
              const SizedBox(height: 12),
              TextField(
                controller: refController,
                decoration: const InputDecoration(labelText: 'UTR / Reference (Optional)', border: OutlineInputBorder()),
              ),
            ],
          ),
          actions: [
            TextButton(onPressed: () => Navigator.pop(ctx), child: const Text('Cancel')),
            ElevatedButton(
              onPressed: () async {
                final amt = double.tryParse(amountController.text) ?? 0.0;
                if (amt <= 0) return;

                final res = await _api.post(ApiConstants.payments, data: {
                  'invoice_id': widget.invoiceId,
                  'amount': amt,
                  'payment_mode': paymentMode,
                  'reference_number': refController.text.trim(),
                });

                if (res.success) {
                  Navigator.pop(ctx);
                  _loadInvoiceDetail();
                }
              },
              child: const Text('Save Payment'),
            ),
          ],
        ),
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    if (_isLoading) {
      return Scaffold(
        appBar: AppBar(title: const Text('Invoice Details')),
        body: const Center(child: CircularProgressIndicator()),
      );
    }

    final inv = _invoice ?? {};
    final totalUnits = (inv['total_units'] as num?)?.toDouble() ?? 0.0;
    final finalAmount = (inv['final_amount'] as num?)?.toDouble() ?? 0.0;
    final isPaid = inv['payment_status'] == 'paid';

    return Scaffold(
      appBar: AppBar(
        title: Text('Invoice #${widget.invoiceId}'),
        actions: [
          IconButton(
            icon: const Icon(Icons.qr_code),
            tooltip: 'Show UPI QR Code',
            onPressed: _showUpiQrDialog,
          ),
          IconButton(
            icon: const Icon(Icons.share),
            tooltip: 'Share on WhatsApp',
            onPressed: _shareOnWhatsApp,
          ),
        ],
      ),
      body: SingleChildScrollView(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            // Bill Header Card
            Card(
              child: Padding(
                padding: const EdgeInsets.all(16),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Row(
                      mainAxisAlignment: MainAxisAlignment.between,
                      children: [
                        Text(
                          inv['customer_name'] ?? 'Customer',
                          style: const TextStyle(fontSize: 18, fontWeight: FontWeight.w900),
                        ),
                        Container(
                          padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
                          decoration: BoxDecoration(
                            color: isPaid ? const Color(0xFFDCFCE7) : const Color(0xFFFEE2E2),
                            borderRadius: BorderRadius.circular(8),
                          ),
                          child: Text(
                            isPaid ? 'PAID' : 'UNPAID',
                            style: TextStyle(
                              fontSize: 11,
                              fontWeight: FontWeight.bold,
                              color: isPaid ? const Color(0xFF15803D) : const Color(0xFFB91C1C),
                            ),
                          ),
                        ),
                      ],
                    ),
                    const SizedBox(height: 4),
                    Text('Phone: ${inv['customer_phone'] ?? ''} • ${inv['route_name'] ?? ''}'),
                    const Divider(height: 24),
                    Row(
                      mainAxisAlignment: MainAxisAlignment.spaceBetween,
                      children: [
                        const Text('Total Milk Volume:'),
                        Text('${totalUnits.toStringAsFixed(1)} Liters', style: const TextStyle(fontWeight: FontWeight.bold)),
                      ],
                    ),
                    const SizedBox(height: 8),
                    Row(
                      mainAxisAlignment: MainAxisAlignment.spaceBetween,
                      children: [
                        const Text('Total Bill Amount:'),
                        Text('₹${finalAmount.toStringAsFixed(2)}', style: const TextStyle(fontWeight: FontWeight.w900, fontSize: 18, color: Color(0xFF0284C7))),
                      ],
                    ),
                  ],
                ),
              ),
            ),
            const SizedBox(height: 16),

            // Action Buttons Bar
            Row(
              children: [
                Expanded(
                  child: ElevatedButton.icon(
                    onPressed: _shareOnWhatsApp,
                    icon: const Icon(Icons.send, size: 18),
                    label: const Text('WhatsApp Bill'),
                    style: ElevatedButton.styleFrom(backgroundColor: const Color(0xFF25D366)),
                  ),
                ),
                const SizedBox(width: 10),
                Expanded(
                  child: ElevatedButton.icon(
                    onPressed: _showUpiQrDialog,
                    icon: const Icon(Icons.qr_code, size: 18),
                    label: const Text('Show UPI QR'),
                    style: ElevatedButton.styleFrom(backgroundColor: const Color(0xFF0284C7)),
                  ),
                ),
              ],
            ),
            const SizedBox(height: 10),
            ElevatedButton.icon(
              onPressed: _showCollectPaymentDialog,
              icon: const Icon(Icons.payment, size: 18),
              label: const Text('Collect Doorstep Payment'),
              style: ElevatedButton.styleFrom(backgroundColor: const Color(0xFF16A34A)),
            ),
            const SizedBox(height: 24),

            // Payments Received History
            const Text('Payment Transactions:', style: TextStyle(fontWeight: FontWeight.bold, fontSize: 14)),
            const SizedBox(height: 8),

            if (_payments.isEmpty)
              const Card(
                child: Padding(
                  padding: EdgeInsets.all(16),
                  child: Center(child: Text('No payments recorded yet')),
                ),
              )
            else
              ..._payments.map((p) {
                return Card(
                  child: ListTile(
                    leading: const CircleAvatar(
                      backgroundColor: Color(0xFFDCFCE7),
                      child: Text('✓', style: TextStyle(color: Color(0xFF15803D), fontWeight: FontWeight.bold)),
                    ),
                    title: Text('₹${(p['amount'] as num?)?.toDouble().toStringAsFixed(2)} via ${(p['payment_mode'] ?? 'cash').toUpperCase()}'),
                    subtitle: Text('Date: ${p['payment_date'] ?? ''} ${p['reference_number'] != null ? '• Ref: ${p['reference_number']}' : ''}'),
                  ),
                );
              }),
          ],
        ),
      ),
    );
  }
}
