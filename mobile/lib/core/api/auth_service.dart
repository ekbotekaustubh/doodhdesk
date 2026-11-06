import 'dart:convert';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';
import 'package:shared_preferences/shared_preferences.dart';
import '../constants/api_constants.dart';
import 'api_client.dart';

class AuthService {
  final ApiClient _api = ApiClient();
  final FlutterSecureStorage _storage = const FlutterSecureStorage();

  static const String _keyToken = 'auth_token';
  static const String _keyUser = 'auth_user';

  Future<ApiResponse> login(String login, String password) async {
    final res = await _api.post(ApiConstants.login, data: {
      'login': login,
      'password': password,
      'device_name': 'DoodhDesk Mobile App',
    });

    if (res.success && res.data != null) {
      final token = res.data['token'];
      final user = res.data['user'] ?? res.data['tenant'];
      if (token != null) {
        await _storage.write(key: _keyToken, value: token);
      }
      if (user != null) {
        final prefs = await SharedPreferences.getInstance();
        await prefs.setString(_keyUser, jsonEncode(user));
      }
    }
    return res;
  }

  Future<void> logout() async {
    try {
      await _api.post(ApiConstants.logout);
    } catch (_) {}
    await _storage.delete(key: _keyToken);
    final prefs = await SharedPreferences.getInstance();
    await prefs.remove(_keyUser);
  }

  Future<bool> isLoggedIn() async {
    final token = await _storage.read(key: _keyToken);
    return token != null && token.isNotEmpty;
  }

  Future<String?> getToken() async {
    return await _storage.read(key: _keyToken);
  }

  Future<Map<String, dynamic>?> getSavedUser() async {
    final prefs = await SharedPreferences.getInstance();
    final str = prefs.getString(_keyUser);
    if (str != null && str.isNotEmpty) {
      return jsonDecode(str);
    }
    return null;
  }
}
