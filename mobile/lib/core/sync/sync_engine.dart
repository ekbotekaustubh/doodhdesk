import 'dart:async';
import 'dart:convert';
import 'package:connectivity_plus/connectivity_plus.dart';
import 'package:uuid/uuid.dart';
import '../api/api_client.dart';
import '../constants/api_constants.dart';
import '../database/database_helper.dart';

class SyncEngine {
  static final SyncEngine _instance = SyncEngine._internal();
  factory SyncEngine() => _instance;

  final DatabaseHelper _db = DatabaseHelper();
  final ApiClient _api = ApiClient();
  final Uuid _uuid = const Uuid();

  bool _isOnline = true;
  bool _isSyncing = false;

  final StreamController<bool> _connectivityController = StreamController<bool>.broadcast();
  Stream<bool> get connectivityStream => _connectivityController.stream;

  final StreamController<int> _outboxCountController = StreamController<int>.broadcast();
  Stream<int> get outboxCountStream => _outboxCountController.stream;

  bool get isOnline => _isOnline;
  bool get isSyncing => _isSyncing;

  SyncEngine._internal() {
    _initConnectivity();
  }

  void _initConnectivity() {
    Connectivity().onConnectivityChanged.listen((List<ConnectivityResult> results) {
      final isConnected = results.any((r) => r != ConnectivityResult.none);
      _isOnline = isConnected;
      _connectivityController.add(_isOnline);

      if (_isOnline) {
        flushOutbox();
      }
    });

    _checkInitialConnectivity();
  }

  Future<void> _checkInitialConnectivity() async {
    final results = await Connectivity().checkConnectivity();
    _isOnline = results.any((r) => r != ConnectivityResult.none);
    _connectivityController.add(_isOnline);
    _notifyOutboxCount();
  }

  Future<void> _notifyOutboxCount() async {
    final count = await _db.getPendingOutboxCount();
    _outboxCountController.add(count);
  }

  /// 1. Optimistic Delivery Update
  /// Updates local SQLite immediately (0ms UI latency) and queues for cloud sync
  Future<void> recordDeliveryOptimistic({
    required int customerId,
    required String date,
    required String shift,
    required String status,
    required double quantity,
    String? notes,
  }) async {
    // A. Update local SQLite cache
    await _db.updateDeliveryLocally(
      customerId: customerId,
      date: date,
      shift: shift,
      status: status,
      quantity: quantity,
      notes: notes,
    );

    // B. Enqueue in Outbox Queue with UUID idempotency key
    final outboxId = _uuid.v4();
    final payload = {
      'idempotency_key': outboxId,
      'customer_id': customerId,
      'delivery_date': date,
      'shift': shift,
      'status': status,
      'quantity': quantity,
      'notes': notes ?? '',
    };

    await _db.enqueueOutbox(
      id: outboxId,
      endpoint: ApiConstants.deliveryToggle,
      method: 'POST',
      payloadJson: jsonEncode(payload),
    );

    await _notifyOutboxCount();

    // C. If online, attempt immediate background flush
    if (_isOnline) {
      flushOutbox();
    }
  }

  /// 2. Flush Outbox Queue when internet is active
  Future<void> flushOutbox() async {
    if (_isSyncing) return;
    _isSyncing = true;

    try {
      final pendingList = await _db.getPendingOutbox();

      for (var task in pendingList) {
        final id = task['id'] as String;
        final endpoint = task['endpoint'] as String;
        final method = task['method'] as String;
        final payload = jsonDecode(task['payload_json'] as String);

        ApiResponse res;
        if (method == 'POST') {
          res = await _api.post(endpoint, data: payload);
        } else if (method == 'PUT') {
          res = await _api.put(endpoint, data: payload);
        } else {
          res = await _api.delete(endpoint, data: payload);
        }

        if (res.success || res.statusCode == 200 || res.statusCode == 201) {
          // Successfully synced, remove task from outbox
          await _db.removeOutbox(id);
        } else if (res.statusCode >= 400 && res.statusCode < 500) {
          // Client validation or business rule conflict, discard or mark resolved
          await _db.removeOutbox(id);
        } else {
          // Server error / connection failure: stop loop and retry next cycle
          break;
        }
      }
    } catch (_) {
      // Offline or network dropped midway
    } finally {
      _isSyncing = false;
      await _notifyOutboxCount();
    }
  }

  /// 3. Refresh today's run-sheet from cloud and cache in local SQLite
  Future<List<Map<String, dynamic>>> syncDeliveries(String date, String shift, {String? route}) async {
    // If online, fetch fresh run-sheet and upsert into SQLite
    if (_isOnline) {
      final res = await _api.get(ApiConstants.deliveries, queryParameters: {
        'date': date,
        'shift': shift,
        'route': route,
      });

      if (res.success && res.data != null) {
        final items = List<Map<String, dynamic>>.from(res.data['items'] ?? []);
        await _db.upsertDeliveries(items, date, shift);
      }
    }

    // Always return from local SQLite (works whether online or offline)
    return await _db.getLocalDeliveries(date, shift, route: route);
  }
}
