class Invoice {
  final int id;
  final int customerId;
  final String customerName;
  final String customerPhone;
  final String? customerAddress;
  final int month;
  final int year;
  final double totalUnits;
  final double subtotalAmount;
  final double taxAmount;
  final double extraCharges;
  final double finalAmount;
  final double paidAmount;
  final double balanceDue;
  final String paymentStatus;
  final String? generatedAt;

  Invoice({
    required this.id,
    required this.customerId,
    required this.customerName,
    required this.customerPhone,
    this.customerAddress,
    required this.month,
    required this.year,
    required this.totalUnits,
    required this.subtotalAmount,
    required this.taxAmount,
    required this.extraCharges,
    required this.finalAmount,
    required this.paidAmount,
    required this.balanceDue,
    required this.paymentStatus,
    this.generatedAt,
  });

  factory Invoice.fromJson(Map<String, dynamic> json) {
    return Invoice(
      id: json['id'] ?? 0,
      customerId: json['customer_id'] ?? 0,
      customerName: json['customer_name'] ?? 'Customer',
      customerPhone: json['customer_phone'] ?? '',
      customerAddress: json['customer_address'],
      month: json['billing_month'] ?? 1,
      year: json['billing_year'] ?? 2026,
      totalUnits: (json['total_units'] as num?)?.toDouble() ?? 0.0,
      subtotalAmount: (json['milk_subtotal'] as num?)?.toDouble() ?? (json['subtotal_amount'] as num?)?.toDouble() ?? 0.0,
      taxAmount: (json['tax_amount'] as num?)?.toDouble() ?? 0.0,
      extraCharges: (json['additional_charges_total'] as num?)?.toDouble() ?? (json['extra_charges'] as num?)?.toDouble() ?? 0.0,
      finalAmount: (json['final_amount'] as num?)?.toDouble() ?? 0.0,
      paidAmount: (json['paid_amount'] as num?)?.toDouble() ?? 0.0,
      balanceDue: (json['balance_due'] as num?)?.toDouble() ?? (json['balance_remaining'] as num?)?.toDouble() ?? 0.0,
      paymentStatus: json['payment_status'] ?? 'unpaid',
      generatedAt: json['generated_at'],
    );
  }
}
