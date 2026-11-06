import 'package:dio/dio.dart';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';
import 'package:shared_preferences/shared_preferences.dart';
import '../constants/api_constants.dart';

class ApiResponse {
  final bool success;
  final int statusCode;
  final dynamic data;
  final String? message;
  final String? error;
  final Map<String, dynamic>? meta;

  ApiResponse({
    required this.success,
    required this.statusCode,
    this.data,
    this.message,
    this.error,
    this.meta,
  });
}

class ApiClient {
  static final ApiClient _instance = ApiClient._internal();
  factory ApiClient() => _instance;

  late Dio _dio;
  final FlutterSecureStorage _secureStorage = const FlutterSecureStorage();
  String _baseUrl = ApiConstants.defaultBaseUrl;

  ApiClient._internal() {
    _dio = Dio(BaseOptions(
      baseUrl: _baseUrl,
      connectTimeout: const Duration(seconds: 10),
      receiveTimeout: const Duration(seconds: 10),
      headers: {
        'Accept': 'application/json',
        'Content-Type': 'application/json',
      },
    ));

    _dio.interceptors.add(InterceptorsWrapper(
      onRequest: (options, handler) async {
        final token = await _secureStorage.read(key: 'auth_token');
        if (token != null && token.isNotEmpty) {
          options.headers['Authorization'] = 'Bearer $token';
        }
        return handler.next(options);
      },
      onError: (DioException e, handler) {
        return handler.next(e);
      },
    ));

    _loadCustomBaseUrl();
  }

  Future<void> _loadCustomBaseUrl() async {
    final prefs = await SharedPreferences.getInstance();
    final savedUrl = prefs.getString('custom_base_url');
    if (savedUrl != null && savedUrl.isNotEmpty) {
      setBaseUrl(savedUrl);
    }
  }

  void setBaseUrl(String url) {
    _baseUrl = url.replaceAll(RegExp(r'/+$'), '');
    _dio.options.baseUrl = _baseUrl;
  }

  String get baseUrl => _baseUrl;

  Future<ApiResponse> get(String path, {Map<String, dynamic>? queryParameters}) async {
    try {
      final response = await _dio.get(path, queryParameters: queryParameters);
      return _formatResponse(response);
    } on DioException catch (e) {
      return _formatError(e);
    }
  }

  Future<ApiResponse> post(String path, {dynamic data}) async {
    try {
      final response = await _dio.post(path, data: data);
      return _formatResponse(response);
    } on DioException catch (e) {
      return _formatError(e);
    }
  }

  Future<ApiResponse> put(String path, {dynamic data}) async {
    try {
      final response = await _dio.put(path, data: data);
      return _formatResponse(response);
    } on DioException catch (e) {
      return _formatError(e);
    }
  }

  Future<ApiResponse> delete(String path, {dynamic data}) async {
    try {
      final response = await _dio.delete(path, data: data);
      return _formatResponse(response);
    } on DioException catch (e) {
      return _formatError(e);
    }
  }

  ApiResponse _formatResponse(Response response) {
    final body = response.data;
    if (body is Map<String, dynamic>) {
      return ApiResponse(
        success: body['success'] ?? (response.statusCode! >= 200 && response.statusCode! < 300),
        statusCode: response.statusCode ?? 200,
        data: body['data'],
        message: body['message'],
        error: body['error'],
        meta: body['meta'] is Map<String, dynamic> ? body['meta'] : null,
      );
    }
    return ApiResponse(
      success: response.statusCode! >= 200 && response.statusCode! < 300,
      statusCode: response.statusCode ?? 200,
      data: body,
    );
  }

  ApiResponse _formatError(DioException e) {
    if (e.response != null && e.response?.data is Map<String, dynamic>) {
      final body = e.response!.data as Map<String, dynamic>;
      return ApiResponse(
        success: false,
        statusCode: e.response?.statusCode ?? 500,
        error: body['error'] ?? body['message'] ?? 'Server error occurred',
        data: body['data'],
        meta: body['meta'] is Map<String, dynamic> ? body['meta'] : null,
      );
    }
    return ApiResponse(
      success: false,
      statusCode: 0,
      error: 'Network connection error. Offline mode active.',
    );
  }
}
