import 'package:dio/dio.dart';
import 'package:e_demand/app/generalImports.dart';

class ApiClient {
  static Map<String, dynamic> headers({bool includeAuth = true}) {
    final String jwtToken = HiveRepository.getUserToken;
    final lang = HiveRepository.getCurrentLanguage();

    if (kDebugMode) {
      print("token is $jwtToken");
    }

    final headers = <String, dynamic>{};

    if (includeAuth) {
      headers["Authorization"] = "Bearer $jwtToken";
    }

    if (lang?.languageCode != null && lang!.languageCode.isNotEmpty) {
      headers["Content-Language"] = lang.languageCode;
    }

    return headers;
  }

  ///post method for API calling
  static Future<Map<String, dynamic>> post({
    required final String url,
    required final Map<String, dynamic> parameter,
    required final bool useAuthToken,
    final b,
  }) async {
    try {
      final Dio dio = Dio();
      dio.interceptors.add(CurlLoggerInterceptor());

      final FormData formData =
          FormData.fromMap(parameter, ListFormat.multiCompatible);

      if (kDebugMode) {
        print("API is $url \n pra are $parameter \n ");
      }

      final response = await dio.post(
        url,
        data: formData,
        options: Options(headers: headers(includeAuth: useAuthToken)),
      );

      if (kDebugMode) {
        print("API is $url \n pra are $parameter \n response is $response");
      }

      if (response.data['error']) {
        throw ApiException(response.data['message']);
      }

      return Map.from(response.data);
    } on DioException catch (e) {
      if (kDebugMode) {
        print("error API is $url");

        print("error is ${e.response} ${e.message}");
      }
      if (e.response?.statusCode == 401) {
        UiUtils.authenticationError = true;
        throw ApiException("authenticationFailed");
      } else if (e.response?.statusCode == 500) {
        throw ApiException("internalServerError");
      }
      throw ApiException(e.error is SocketException
          ? "noInternetFound"
          : "somethingWentWrong");
    } on ApiException catch (e) {
      throw ApiException(e.errorMessage);
    } catch (e, st) {
      if (kDebugMode) {
        print("api $e ${st.toString()}");
      }
      throw ApiException("somethingWentWrong");
    }
  }

  ///post method for API calling
  static Future<List<int>> downloadAPI({
    required final String url,
    required final Map<String, dynamic> parameter,
    required final bool useAuthToken,
    final bool? isInvoiceAPI,
  }) async {
    try {
      final Dio dio = Dio();
      dio.interceptors.add(CurlLoggerInterceptor());
      final FormData formData =
          FormData.fromMap(parameter, ListFormat.multiCompatible);

      final response = await dio.post(
        url,
        data: formData,
        options: Options(headers: {
          ...headers(includeAuth: useAuthToken),
          'Accept': 'application/pdf',
        }, responseType: ResponseType.bytes),
      );

      return response.data;
    } on DioException catch (e) {
      if (e.response?.statusCode == 401) {
        UiUtils.authenticationError = true;
        throw ApiException("authenticationFailed");
      } else if (e.response?.statusCode == 500) {
        throw ApiException("internalServerError");
      } else if (e.response?.statusCode == 503) {
        throw ApiException("internalServerError");
      }
      throw ApiException(e.error is SocketException
          ? "noInternetFound"
          : "somethingWentWrong");
    } on ApiException {
      throw ApiException("somethingWentWrong");
    } catch (e) {
      throw ApiException("somethingWentWrong");
    }
  }

  static Future<Map<String, dynamic>> get({
    required final String url,
    required final bool useAuthToken,
    final Map<String, dynamic>? queryParameters,
  }) async {
    try {
      final Dio dio = Dio();
      dio.interceptors.add(CurlLoggerInterceptor());
      final response = await dio.get(
        url,
        queryParameters: queryParameters,
        options: Options(headers: headers(includeAuth: useAuthToken)),
      );

      if (response.data['error'] == true) {
        throw ApiException(response.data['code'].toString());
      }

      return Map.from(response.data);
    } on DioException catch (e) {
      if (e.response?.statusCode == 401) {
        UiUtils.authenticationError = true;
        throw ApiException("authenticationFailed");
      } else if (e.response?.statusCode == 500) {
        throw ApiException("internalServerError");
      }
      throw ApiException(e.error is SocketException
          ? "noInternetFound"
          : "somethingWentWrong");
    } on ApiException {
      throw ApiException("somethingWentWrong");
    } catch (e) {
      throw ApiException("somethingWentWrong");
    }
  }
}
