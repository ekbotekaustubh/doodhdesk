import 'package:flutter/material.dart';
import 'package:intl/intl.dart';
import 'package:url_launcher/url_launcher.dart';
import '../core/sync/sync_engine.dart';
import '../models/delivery_item.dart';

class DeliveryScreen extends StatefulWidget {
  const DeliveryScreen({super.key});

  @override
  State<DeliveryScreen> createState() => _DeliveryScreenState();
}

class _DeliveryScreenState extends State<DeliveryScreen> {
  final SyncEngine _syncEngine = SyncEngine();

  DateTime _selectedDate = DateTime.now();
  String _selectedShift = DateTime.now().hour >= 14 ? 'evening' : 'morning';
  String? _selectedRoute;

  List<DeliveryItem> _items = [];
  List<String> _routes = [];
  bool _isLoading = false;
  int _pendingSyncCount = 0;

  @override
  void initState() {
    super.initState();
    _loadDeliveries();

    _syncEngine.outboxCountStream.listen((count) {
      if (mounted) setState(() => _pendingSyncCount = count);
    });

    _syncEngine.connectivityStream.listen((isOnline) {
      if (mounted) setState(() {});
    });
  }

  String get _dateStr => DateFormat('yyyy-MM-dd').format(_selectedDate);

  Future<void> _loadDeliveries() async {
    setState(() => _isLoading = true);

    try {
      final rawList = await _syncEngine.syncDeliveries(
        _dateStr,
        _selectedShift,
        route: _selectedRoute,
      );

      final items = rawList.map((m) => DeliveryItem.fromMap(m)).toList();
      final routesSet = items.map((e) => e.routeName).where((r) => r.isNotEmpty).toSet();

      setState(() {
        _items = items;
        _routes = routesSet.toList();
      });
    } catch (_) {
    } finally {
      setState(() => _isLoading = false);
    }
  }

  Future<void> _toggleDelivery(DeliveryItem item, String newStatus, double quantity) async {
    // 1. Optimistic Local UI update
    setState(() {
      final index = _items.indexWhere((it) => it.customerId == item.customerId);
      if (index != -1) {
        _items[index] = item.copyWith(
          status: newStatus,
          quantity: quantity,
          synced: false,
        );
      }
    });

    // 2. Queue in offline Outbox & background flush
    await _syncEngine.recordDeliveryOptimistic(
      customerId: item.customerId,
      date: _dateStr,
      shift: _selectedShift,
      status: newStatus,
      quantity: quantity,
      notes: item.notes,
    );
  }

  void _showAdjustDialog(DeliveryItem item) {
    double tempQty = item.quantity > 0 ? item.quantity : 1.0;
    final noteController = TextEditingController(text: item.notes);

    showModalBottomSheet(
      context: context,
      isScrollControlled: true,
      shape: const RoundedRectangleBorder(
        borderRadius: BorderRadius.vertical(top: Radius.circular(20)),
      ),
      builder: (ctx) {
        return StatefulBuilder(
          builder: (context, setModalState) {
            return Padding(
              padding: EdgeInsets.only(
                left: 20,
                right: 20,
                top: 20,
                bottom: MediaQuery.of(ctx).viewInsets.bottom + 20,
              ),
              child: Column(
                mainAxisSize: MainAxisSize.min,
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  Row(
                    mainAxisAlignment: MainAxisAlignment.between,
                    children: [
                      Text(
                        'Adjust: ${item.customerName}',
                        style: const TextStyle(fontSize: 16, fontWeight: FontWeight.bold),
                      ),
                      IconButton(
                        icon: const Icon(Icons.close),
                        onPressed: () => Navigator.pop(ctx),
                      ),
                    ],
                  ),
                  const Divider(),
                  const SizedBox(height: 12),
                  const Text(
                    'Delivered Quantity (Liters)',
                    style: TextStyle(fontSize: 12, fontWeight: FontWeight.bold, color: Colors.black54),
                  ),
                  const SizedBox(height: 12),
                  Row(
                    mainAxisAlignment: MainAxisAlignment.center,
                    children: [
                      IconButton.filledTonal(
                        icon: const Icon(Icons.remove),
                        onPressed: () {
                          if (tempQty >= 0.5) {
                            setModalState(() => tempQty = double.parse((tempQty - 0.5).toStringAsFixed(1)));
                          }
                        },
                      ),
                      const SizedBox(width: 24),
                      Text(
                        '${tempQty.toStringAsFixed(1)} L',
                        style: const TextStyle(fontSize: 32, fontWeight: FontWeight.w900),
                      ),
                      const SizedBox(width: 24),
                      IconButton.filledTonal(
                        icon: const Icon(Icons.add),
                        onPressed: () {
                          setModalState(() => tempQty = double.parse((tempQty + 0.5).toStringAsFixed(1)));
                        },
                      ),
                    ],
                  ),
                  const SizedBox(height: 16),
                  TextField(
                    controller: noteController,
                    decoration: InputDecoration(
                      labelText: 'Note / Reason',
                      hintText: 'e.g. Extra milk requested',
                      border: OutlineInputBorder(borderRadius: BorderRadius.circular(10)),
                      contentPadding: const EdgeInsets.symmetric(horizontal: 12, vertical: 10),
                    ),
                  ),
                  const SizedBox(height: 20),
                  ElevatedButton(
                    onPressed: () {
                      Navigator.pop(ctx);
                      _toggleDelivery(item, tempQty > 0 ? 'delivered' : 'skipped', tempQty);
                    },
                    style: ElevatedButton.styleFrom(
                      backgroundColor: const Color(0xFF0284C7),
                      padding: const EdgeInsets.symmetric(vertical: 14),
                    ),
                    child: const Text('Save Volume Adjustment', style: TextStyle(color: Colors.white, fontWeight: FontWeight.bold)),
                  ),
                ],
              ),
            );
          },
        );
      },
    );
  }

  Future<void> _makePhoneCall(String phone) async {
    final clean = phone.replaceAll(RegExp(r'[^0-9]'), '');
    final uri = Uri.parse('tel:$clean');
    if (await canLaunchUrl(uri)) {
      await launchUrl(uri);
    }
  }

  @override
  Widget build(BuildContext context) {
    final totalDelivered = _items.where((i) => i.status == 'delivered').length;
    final totalLiters = _items
        .where((i) => i.status == 'delivered')
        .fold(0.0, (sum, i) => sum + i.quantity);

    return Scaffold(
      appBar: AppBar(
        title: const Text('📋 Daily Delivery Run-Sheet'),
        actions: [
          IconButton(
            icon: const Icon(Icons.sync),
            tooltip: 'Sync Offline Changes',
            onPressed: () {
              _syncEngine.flushOutbox();
              _loadDeliveries();
            },
          ),
        ],
      ),
      body: Column(
        children: [
          // Offline Banner Indicator
          if (!_syncEngine.isOnline || _pendingSyncCount > 0)
            Container(
              padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 8),
              color: !_syncEngine.isOnline ? const Color(0xFFFEF3C7) : const Color(0xFFEFF6FF),
              child: Row(
                children: [
                  Icon(
                    !_syncEngine.isOnline ? Icons.wifi_off : Icons.cloud_upload_outlined,
                    size: 16,
                    color: !_syncEngine.isOnline ? const Color(0xFFD97706) : const Color(0xFF2563EB),
                  ),
                  const SizedBox(width: 8),
                  Expanded(
                    child: Text(
                      !_syncEngine.isOnline
                          ? '⚡ Offline Mode Active ($_pendingSyncCount pending sync)'
                          : 'Syncing $_pendingSyncCount update(s) to cloud...',
                      style: TextStyle(
                        fontSize: 12,
                        fontWeight: FontWeight.bold,
                        color: !_syncEngine.isOnline ? const Color(0xFF92400E) : const Color(0xFF1E40AF),
                      ),
                    ),
                  ),
                  if (_syncEngine.isOnline)
                    TextButton(
                      onPressed: () => _syncEngine.flushOutbox(),
                      child: const Text('Sync Now', style: TextStyle(fontSize: 11, fontWeight: FontWeight.bold)),
                    ),
                ],
              ),
            ),

          // Date & Shift Bar
          Container(
            padding: const EdgeInsets.all(12),
            color: Colors.white,
            child: Column(
              children: [
                Row(
                  children: [
                    // Date Navigation
                    IconButton(
                      icon: const Icon(Icons.chevron_left),
                      onPressed: () {
                        setState(() {
                          _selectedDate = _selectedDate.subtract(const Duration(days: 1));
                        });
                        _loadDeliveries();
                      },
                    ),
                    Expanded(
                      child: InkWell(
                        onTap: () async {
                          final picked = await showDatePicker(
                            context: context,
                            initialDate: _selectedDate,
                            firstDate: DateTime(2020),
                            lastDate: DateTime(2030),
                          );
                          if (picked != null) {
                            setState(() => _selectedDate = picked);
                            _loadDeliveries();
                          }
                        },
                        child: Text(
                          DateFormat('EEE, dd MMM yyyy').format(_selectedDate),
                          textAlign: TextAlign.center,
                          style: const TextStyle(fontWeight: FontWeight.w900, fontSize: 15),
                        ),
                      ),
                    ),
                    IconButton(
                      icon: const Icon(Icons.chevron_right),
                      onPressed: () {
                        setState(() {
                          _selectedDate = _selectedDate.add(const Duration(days: 1));
                        });
                        _loadDeliveries();
                      },
                    ),
                  ],
                ),
                const SizedBox(height: 8),

                // Shift Toggle (Morning vs Evening)
                Row(
                  children: [
                    Expanded(
                      child: ChoiceChip(
                        label: const Row(
                          mainAxisAlignment: MainAxisAlignment.center,
                          children: [
                            Text('🌅 Morning'),
                          ],
                        ),
                        selected: _selectedShift == 'morning',
                        selectedColor: const Color(0xFFFEF3C7),
                        onSelected: (val) {
                          if (val) {
                            setState(() => _selectedShift = 'morning');
                            _loadDeliveries();
                          }
                        },
                      ),
                    ),
                    const SizedBox(width: 8),
                    Expanded(
                      child: ChoiceChip(
                        label: const Row(
                          mainAxisAlignment: MainAxisAlignment.center,
                          children: [
                            Text('🌆 Evening'),
                          ],
                        ),
                        selected: _selectedShift == 'evening',
                        selectedColor: const Color(0xFFE0E7FF),
                        onSelected: (val) {
                          if (val) {
                            setState(() => _selectedShift = 'evening');
                            _loadDeliveries();
                          }
                        },
                      ),
                    ),
                  ],
                ),
              ],
            ),
          ),

          // Shift Metrics Strip
          Container(
            padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 10),
            color: const Color(0xFFF1F5F9),
            child: Row(
              mainAxisAlignment: MainAxisAlignment.spaceBetween,
              children: [
                Text(
                  'Delivered: $totalDelivered/${_items.length} Houses',
                  style: const TextStyle(fontSize: 12, fontWeight: FontWeight.bold, color: Color(0xFF334155)),
                ),
                Text(
                  'Volume: ${totalLiters.toStringAsFixed(1)} Liters',
                  style: const TextStyle(fontSize: 12, fontWeight: FontWeight.w900, color: Color(0xFF0284C7)),
                ),
              ],
            ),
          ),

          // Run-Sheet Cards List
          Expanded(
            child: _isLoading
                ? const Center(child: CircularProgressIndicator())
                : _items.isEmpty
                    ? Center(
                        child: Column(
                          mainAxisAlignment: MainAxisAlignment.center,
                          children: [
                            const Text('👥', style: TextStyle(fontSize: 40)),
                            const SizedBox(height: 8),
                            const Text('No deliveries scheduled for this shift', style: TextStyle(fontWeight: FontWeight.bold)),
                            const SizedBox(height: 12),
                            ElevatedButton(
                              onPressed: _loadDeliveries,
                              style: ElevatedButton.styleFrom(minimumSize: const Size(120, 36)),
                              child: const Text('Refresh'),
                            )
                          ],
                        ),
                      )
                    : RefreshIndicator(
                        onRefresh: _loadDeliveries,
                        child: ListView.builder(
                          padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
                          itemCount: _items.length,
                          itemBuilder: (context, idx) {
                            final item = _items[idx];
                            return _buildDeliveryCard(item);
                          },
                        ),
                      ),
          ),
        ],
      ),
    );
  }

  Widget _buildDeliveryCard(DeliveryItem item) {
    final isDelivered = item.status == 'delivered';
    final isSkipped = item.status == 'skipped';
    final isOnLeave = item.isOnLeave || item.status == 'leave';

    return Card(
      margin: const EdgeInsets.symmetric(vertical: 6),
      child: Padding(
        padding: const EdgeInsets.all(14),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            // Top Row: Customer Name & Phone
            Row(
              mainAxisAlignment: MainAxisAlignment.between,
              children: [
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        item.customerName,
                        style: const TextStyle(fontSize: 16, fontWeight: FontWeight.w900, color: Color(0xFF0F172A)),
                      ),
                      const SizedBox(height: 2),
                      Text(
                        '${item.routeName} • ${item.address}',
                        style: const TextStyle(fontSize: 11, color: Color(0xFF64748B)),
                        maxLines: 1,
                        overflow: TextOverflow.ellipsis,
                      ),
                    ],
                  ),
                ),
                if (item.customerPhone.isNotEmpty)
                  IconButton.filledTonal(
                    icon: const Icon(Icons.phone, size: 18),
                    onPressed: () => _makePhoneCall(item.customerPhone),
                  ),
              ],
            ),
            const SizedBox(height: 10),

            // Products & Quantity Info
            Row(
              mainAxisAlignment: MainAxisAlignment.between,
              children: [
                Container(
                  padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
                  decoration: BoxDecoration(
                    color: const Color(0xFFF1F5F9),
                    borderRadius: BorderRadius.circular(8),
                  ),
                  child: Text(
                    '${item.productName}: ${item.quantity.toStringAsFixed(1)} ${item.unit}',
                    style: const TextStyle(fontSize: 12, fontWeight: FontWeight.bold, color: Color(0xFF334155)),
                  ),
                ),
                if (!item.synced)
                  const Row(
                    children: [
                      Icon(Icons.cloud_upload_outlined, size: 14, color: Colors.orange),
                      SizedBox(width: 4),
                      Text('Queued offline', style: TextStyle(fontSize: 10, color: Colors.orange, fontWeight: FontWeight.bold)),
                    ],
                  ),
              ],
            ),
            const SizedBox(height: 12),

            // Field Action Buttons (High contrast, 48px thumb targets)
            if (isOnLeave)
              Container(
                width: double.infinity,
                padding: const EdgeInsets.symmetric(vertical: 10),
                decoration: BoxDecoration(
                  color: const Color(0xFFF1F5F9),
                  borderRadius: BorderRadius.circular(10),
                ),
                child: const Center(
                  child: Text('🏖 Customer On Vacation (0.00L Billed)', style: TextStyle(fontSize: 12, fontWeight: FontWeight.bold, color: Colors.black54)),
                ),
              )
            else
              Row(
                children: [
                  // Delivered Button
                  Expanded(
                    child: ElevatedButton.icon(
                      onPressed: () => _toggleDelivery(item, 'delivered', item.quantity > 0 ? item.quantity : 1.0),
                      icon: const Icon(Icons.check, size: 18),
                      label: const Text('Delivered'),
                      style: ElevatedButton.styleFrom(
                        backgroundColor: isDelivered ? const Color(0xFF16A34A) : Colors.white,
                        foregroundColor: isDelivered ? Colors.white : const Color(0xFF16A34A),
                        side: BorderSide(color: const Color(0xFF16A34A), width: isDelivered ? 0 : 1.5),
                        elevation: isDelivered ? 2 : 0,
                      ),
                    ),
                  ),
                  const SizedBox(width: 8),

                  // Skipped Button
                  Expanded(
                    child: ElevatedButton.icon(
                      onPressed: () => _toggleDelivery(item, 'skipped', 0.0),
                      icon: const Icon(Icons.close, size: 18),
                      label: const Text('Skipped'),
                      style: ElevatedButton.styleFrom(
                        backgroundColor: isSkipped ? const Color(0xFFDC2626) : Colors.white,
                        foregroundColor: isSkipped ? Colors.white : const Color(0xFFDC2626),
                        side: BorderSide(color: const Color(0xFFDC2626), width: isSkipped ? 0 : 1.5),
                        elevation: isSkipped ? 2 : 0,
                      ),
                    ),
                  ),
                  const SizedBox(width: 8),

                  // Edit Volume Button
                  OutlinedButton(
                    onPressed: () => _showAdjustDialog(item),
                    style: OutlinedButton.styleFrom(
                      padding: const EdgeInsets.symmetric(horizontal: 12),
                    ),
                    child: const Text('✏️ Edit'),
                  ),
                ],
              ),
          ],
        ),
      ),
    );
  }
}
