import 'package:sqflite/sqflite.dart';
import 'package:path/path.dart';

class DatabaseHelper {
  static final DatabaseHelper _instance = DatabaseHelper._internal();
  factory DatabaseHelper() => _instance;
  DatabaseHelper._internal();

  static Database? _database;

  Future<Database> get database async {
    if (_database != null) return _database!;
    _database = await _initDatabase();
    return _database!;
  }

  Future<Database> _initDatabase() async {
    final dbPath = await getDatabasesPath();
    final path = join(dbPath, 'doodhdesk_offline.db');

    return await openDatabase(
      path,
      version: 1,
      onCreate: _createTables,
    );
  }

  Future<void> _createTables(Database db, int version) async {
    // 1. Deliveries Run-Sheet Table
    await db.execute('''
      CREATE TABLE deliveries (
        local_id INTEGER PRIMARY KEY AUTOINCREMENT,
        server_id INTEGER,
        customer_id INTEGER NOT NULL,
        customer_name TEXT NOT NULL,
        customer_phone TEXT,
        address TEXT,
        route_name TEXT,
        product_id INTEGER,
        product_name TEXT,
        unit TEXT DEFAULT 'L',
        delivery_date TEXT NOT NULL,
        shift TEXT NOT NULL,
        quantity REAL NOT NULL,
        status TEXT NOT NULL,
        notes TEXT,
        is_on_leave INTEGER DEFAULT 0,
        synced INTEGER DEFAULT 1
      )
    ''');

    // 2. Customers Table
    await db.execute('''
      CREATE TABLE customers (
        id INTEGER PRIMARY KEY,
        name TEXT NOT NULL,
        phone TEXT NOT NULL,
        address TEXT,
        route_name TEXT,
        milk_types TEXT,
        status TEXT DEFAULT 'active'
      )
    ''');

    // 3. Offline Outbox Queue (FIFO mutations to sync to server)
    await db.execute('''
      CREATE TABLE sync_outbox (
        id TEXT PRIMARY KEY,
        endpoint TEXT NOT NULL,
        method TEXT NOT NULL,
        payload_json TEXT NOT NULL,
        created_at TEXT NOT NULL,
        retry_count INTEGER DEFAULT 0,
        status TEXT DEFAULT 'pending'
      )
    ''');
  }

  // Helper Methods for Deliveries
  Future<void> upsertDeliveries(List<Map<String, dynamic>> items, String date, String shift) async {
    final db = await database;
    final batch = db.batch();

    // Clear old synced items for this date/shift before repopulating
    await db.delete(
      'deliveries',
      where: 'delivery_date = ? AND shift = ? AND synced = 1',
      whereArgs: [date, shift],
    );

    for (var it in items) {
      batch.insert('deliveries', {
        'server_id': it['id'],
        'customer_id': it['customer_id'],
        'customer_name': it['customer_name'] ?? 'Customer',
        'customer_phone': it['customer_phone'] ?? '',
        'address': it['address'] ?? '',
        'route_name': it['route_name'] ?? 'Main Route',
        'product_id': it['product_id'] ?? 1,
        'product_name': it['product_name'] ?? 'Fresh Milk',
        'unit': it['unit'] ?? 'L',
        'delivery_date': date,
        'shift': shift,
        'quantity': (it['quantity'] as num?)?.toDouble() ?? 1.0,
        'status': it['status'] ?? 'pending',
        'notes': it['notes'] ?? '',
        'is_on_leave': (it['is_on_leave'] == true || it['status'] == 'leave') ? 1 : 0,
        'synced': 1,
      });
    }

    await batch.commit(noResult: true);
  }

  Future<List<Map<String, dynamic>>> getLocalDeliveries(String date, String shift, {String? route}) async {
    final db = await database;
    String where = 'delivery_date = ? AND shift = ?';
    List<dynamic> whereArgs = [date, shift];

    if (route != null && route.isNotEmpty) {
      where += ' AND route_name = ?';
      whereArgs.add(route);
    }

    return await db.query(
      'deliveries',
      where: where,
      whereArgs: whereArgs,
      orderBy: 'route_name ASC, customer_name ASC',
    );
  }

  Future<void> updateDeliveryLocally({
    required int customerId,
    required String date,
    required String shift,
    required String status,
    required double quantity,
    String? notes,
  }) async {
    final db = await database;
    await db.update(
      'deliveries',
      {
        'status': status,
        'quantity': quantity,
        'notes': notes ?? '',
        'synced': 0, // Mark as dirty/pending offline sync
      },
      where: 'customer_id = ? AND delivery_date = ? AND shift = ?',
      whereArgs: [customerId, date, shift],
    );
  }

  // Outbox Queue Methods
  Future<void> enqueueOutbox({
    required String id,
    required String endpoint,
    required String method,
    required String payloadJson,
  }) async {
    final db = await database;
    await db.insert('sync_outbox', {
      'id': id,
      'endpoint': endpoint,
      'method': method,
      'payload_json': payloadJson,
      'created_at': DateTime.now().toIso8601String(),
      'retry_count': 0,
      'status': 'pending',
    });
  }

  Future<List<Map<String, dynamic>>> getPendingOutbox() async {
    final db = await database;
    return await db.query(
      'sync_outbox',
      where: "status = 'pending'",
      orderBy: 'created_at ASC',
    );
  }

  Future<void> removeOutbox(String id) async {
    final db = await database;
    await db.delete('sync_outbox', where: 'id = ?', whereArgs: [id]);
  }

  Future<int> getPendingOutboxCount() async {
    final db = await database;
    final res = await db.rawQuery("SELECT COUNT(*) as count FROM sync_outbox WHERE status = 'pending'");
    return Sqflite.firstIntValue(res) ?? 0;
  }
}
