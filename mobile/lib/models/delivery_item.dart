class DeliveryItem {
  final int? localId;
  final int? serverId;
  final int customerId;
  final String customerName;
  final String customerPhone;
  final String address;
  final String routeName;
  final int productId;
  final String productName;
  final String unit;
  final String deliveryDate;
  final String shift;
  final double quantity;
  final String status;
  final String notes;
  final bool isOnLeave;
  final bool synced;

  DeliveryItem({
    this.localId,
    this.serverId,
    required this.customerId,
    required this.customerName,
    required this.customerPhone,
    required this.address,
    required this.routeName,
    required this.productId,
    required this.productName,
    required this.unit,
    required this.deliveryDate,
    required this.shift,
    required this.quantity,
    required this.status,
    required this.notes,
    required this.isOnLeave,
    required this.synced,
  });

  factory DeliveryItem.fromMap(Map<String, dynamic> map) {
    return DeliveryItem(
      localId: map['local_id'],
      serverId: map['server_id'],
      customerId: map['customer_id'] ?? 0,
      customerName: map['customer_name'] ?? 'Customer',
      customerPhone: map['customer_phone'] ?? '',
      address: map['address'] ?? '',
      routeName: map['route_name'] ?? 'Main Route',
      productId: map['product_id'] ?? 1,
      productName: map['product_name'] ?? 'Fresh Milk',
      unit: map['unit'] ?? 'L',
      deliveryDate: map['delivery_date'] ?? '',
      shift: map['shift'] ?? 'morning',
      quantity: (map['quantity'] as num?)?.toDouble() ?? 0.0,
      status: map['status'] ?? 'pending',
      notes: map['notes'] ?? '',
      isOnLeave: map['is_on_leave'] == 1 || map['is_on_leave'] == true,
      synced: map['synced'] == 1,
    );
  }

  DeliveryItem copyWith({
    String? status,
    double? quantity,
    String? notes,
    bool? synced,
  }) {
    return DeliveryItem(
      localId: localId,
      serverId: serverId,
      customerId: customerId,
      customerName: customerName,
      customerPhone: customerPhone,
      address: address,
      routeName: routeName,
      productId: productId,
      productName: productName,
      unit: unit,
      deliveryDate: deliveryDate,
      shift: shift,
      quantity: quantity ?? this.quantity,
      status: status ?? this.status,
      notes: notes ?? this.notes,
      isOnLeave: isOnLeave,
      synced: synced ?? this.synced,
    );
  }
}
