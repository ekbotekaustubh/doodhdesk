import 'package:flutter/material.dart';
import 'package:intl/intl.dart';
import '../core/api/api_client.dart';
import '../core/constants/api_constants.dart';

class CustomerLeaveScreen extends StatefulWidget {
  final int customerId;
  final String customerName;

  const CustomerLeaveScreen({
    super.key,
    required this.customerId,
    required this.customerName,
  });

  @override
  State<CustomerLeaveScreen> createState() => _CustomerLeaveScreenState();
}

class _CustomerLeaveScreenState extends State<CustomerLeaveScreen> {
  final ApiClient _api = ApiClient();

  DateTime _startDate = DateTime.now().add(const Duration(days: 1));
  DateTime _endDate = DateTime.now().add(const Duration(days: 5));
  final _reasonController = TextEditingController(text: 'Vacation / Going out of town');

  List<dynamic> _leaves = [];
  bool _isLoading = false;

  @override
  void initState() {
    super.initState();
    _loadLeaves();
  }

  Future<void> _loadLeaves() async {
    setState(() => _isLoading = true);
    final res = await _api.get('${ApiConstants.customers}/${widget.customerId}/leaves');
    if (res.success && res.data != null) {
      setState(() => _leaves = res.data as List);
    }
    setState(() => _isLoading = false);
  }

  Future<void> _scheduleLeave() async {
    final startStr = DateFormat('yyyy-MM-dd').format(_startDate);
    final endStr = DateFormat('yyyy-MM-dd').format(_endDate);

    final res = await _api.post('${ApiConstants.customers}/${widget.customerId}/leaves', data: {
      'start_date': startStr,
      'end_date': endStr,
      'reason': _reasonController.text.trim(),
    });

    if (res.success) {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text('Vacation paused from $startStr to $endStr')),
      );
      _loadLeaves();
    }
  }

  Future<void> _deleteLeave(int leaveId) async {
    final res = await _api.delete('${ApiConstants.customers}/${widget.customerId}/leaves/$leaveId');
    if (res.success) {
      _loadLeaves();
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: Text('🏖 Pause Deliveries: ${widget.customerName}'),
      ),
      body: SingleChildScrollView(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            Card(
              child: Padding(
                padding: const EdgeInsets.all(16),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    const Text(
                      'Schedule Vacation Pause',
                      style: TextStyle(fontWeight: FontWeight.bold, fontSize: 16),
                    ),
                    const SizedBox(height: 6),
                    const Text(
                      'Deliveries will be auto-paused (0.00L billed) during these dates.',
                      style: TextStyle(fontSize: 12, color: Colors.black54),
                    ),
                    const SizedBox(height: 16),
                    Row(
                      children: [
                        Expanded(
                          child: OutlinedButton(
                            onPressed: () async {
                              final picked = await showDatePicker(
                                context: context,
                                initialDate: _startDate,
                                firstDate: DateTime.now(),
                                lastDate: DateTime.now().add(const Duration(days: 90)),
                              );
                              if (picked != null) setState(() => _startDate = picked);
                            },
                            child: Text('Start: ${DateFormat('dd MMM').format(_startDate)}'),
                          ),
                        ),
                        const SizedBox(width: 10),
                        Expanded(
                          child: OutlinedButton(
                            onPressed: () async {
                              final picked = await showDatePicker(
                                context: context,
                                initialDate: _endDate,
                                firstDate: _startDate,
                                lastDate: DateTime.now().add(const Duration(days: 90)),
                              );
                              if (picked != null) setState(() => _endDate = picked);
                            },
                            child: Text('End: ${DateFormat('dd MMM').format(_endDate)}'),
                          ),
                        ),
                      ],
                    ),
                    const SizedBox(height: 12),
                    TextField(
                      controller: _reasonController,
                      decoration: const InputDecoration(
                        labelText: 'Reason',
                        border: OutlineInputBorder(),
                        contentPadding: EdgeInsets.symmetric(horizontal: 12, vertical: 10),
                      ),
                    ),
                    const SizedBox(height: 14),
                    ElevatedButton(
                      onPressed: _scheduleLeave,
                      child: const Text('Schedule Vacation Pause', style: TextStyle(color: Colors.white, fontWeight: FontWeight.bold)),
                    ),
                  ],
                ),
              ),
            ),
            const SizedBox(height: 20),

            const Text(
              'Scheduled Vacations:',
              style: TextStyle(fontWeight: FontWeight.bold, fontSize: 14),
            ),
            const SizedBox(height: 8),

            if (_isLoading)
              const Center(child: CircularProgressIndicator())
            else if (_leaves.isEmpty)
              const Padding(
                padding: EdgeInsets.all(16),
                child: Center(child: Text('No active vacation schedules')),
              )
            else
              ..._leaves.map((l) {
                return Card(
                  child: ListTile(
                    leading: const CircleAvatar(
                      backgroundColor: Color(0xFFFEF3C7),
                      child: Text('🏖'),
                    ),
                    title: Text('${l['start_date']} to ${l['end_date']}'),
                    subtitle: Text(l['reason'] ?? 'Pause'),
                    trailing: IconButton(
                      icon: const Icon(Icons.delete_outline, color: Colors.red),
                      onPressed: () => _deleteLeave(l['id']),
                    ),
                  ),
                );
              }),
          ],
        ),
      ),
    );
  }
}
