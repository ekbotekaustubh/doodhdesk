class CustomerSubscription {
  final int id;
  final int productId;
  final String productName;
  final String productCode;
  final String unit;
  final double defaultQuantity;
  final double? customRate;
  final double effectiveRate;
  final String shift;

  CustomerSubscription({
    required this.id,
    required this.productId,
    required this.productName,
    required this.productCode,
    required this.unit,
    required this.defaultQuantity,
    this.customRate,
    required this.effectiveRate,
    required this.shift,
  });

  factory CustomerSubscription.fromJson(Map<String, dynamic> json) {
    return CustomerSubscription(
      id: json['id'] ?? 0,
      productId: json['product_id'] ?? 0,
      productName: json['product_name'] ?? 'Milk',
      productCode: json['product_code'] ?? 'cow',
      unit: json['unit'] ?? 'L',
      defaultQuantity: (json['default_quantity'] as num?)?.toDouble() ?? 1.0,
      customRate: (json['custom_rate'] as num?)?.toDouble(),
      effectiveRate: (json['effective_rate'] as num?)?.toDouble() ?? 60.0,
      shift: json['shift'] ?? 'both',
    );
  }
}

class Customer {
  final int id;
  final String name;
  final String phone;
  final String address;
  final String routeName;
  final String milkTypes;
  final String status;
  final List<CustomerSubscription> subscriptions;
  final double totalDailyQuantity;

  Customer({
    required this.id,
    required this.name,
    required this.phone,
    required this.address,
    required this.routeName,
    required this.milkTypes,
    required this.status,
    required this.subscriptions,
    required this.totalDailyQuantity,
  });

  factory Customer.fromJson(Map<String, dynamic> json) {
    final rawSubs = json['subscriptions'] as List? ?? [];
    final subs = rawSubs.map((s) => CustomerSubscription.fromJson(s)).toList();

    return Customer(
      id: json['id'] ?? 0,
      name: json['name'] ?? '',
      phone: json['phone'] ?? '',
      address: json['address'] ?? '',
      routeName: json['route_name'] ?? 'Main Route',
      milkTypes: json['milk_types'] ?? 'cow',
      status: json['status'] ?? 'active',
      subscriptions: subs,
      totalDailyQuantity: (json['total_daily_quantity'] as num?)?.toDouble() ??
          subs.fold(0.0, (sum, s) => sum + s.defaultQuantity),
    );
  }
}
