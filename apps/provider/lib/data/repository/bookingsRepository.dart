import 'package:http_parser/http_parser.dart';
import 'package:path/path.dart' as p;

import '../../app/generalImports.dart';

class BookingsRepository {
  Future<DataOutput<BookingsModel>> fetchBooking({
    required int offset,
    required String? status,
    String? customRequestOrder,
    String? fetchBothBookings,
  }) async {
    try {
      final Map<String, dynamic> response = await ApiClient.post(
        url: ApiUrl.getBookings,
        parameter: {
          ApiParam.limit: UiUtils.limit,
          ApiParam.offset: offset,
          ApiParam.status: status,
          ApiParam.order: 'DESC',
          ApiParam.customRequestOrders: customRequestOrder ?? '',
          ApiParam.fetchBothBookings: fetchBothBookings,
        },
        useAuthToken: true,
      );
      List<BookingsModel> modelList;
      if (response['data'].isEmpty) {
        modelList = [];
      } else {
        modelList = (response['data']['data'] as List).map((element) {
          return BookingsModel.fromJson(element);
        }).toList();
      }

      return DataOutput<BookingsModel>(
        total: int.parse(response['total'] ?? '0'),
        modelList: modelList,
      );
    } catch (e) {
      throw ApiException(e.toString());
    }
  }

  Future<Map<String, dynamic>> updateBookingStatus({
    required int orderId,
    required int customerId,
    required String status,
    required String otp,
    List<Map<String, dynamic>>? proofData,
    List<Map<String, dynamic>>? additionalCharges,
    String? date,
    String? time,
  }) async {
    try {
      final Map<String, dynamic> parameters = {
        ApiParam.orderId: orderId,
        ApiParam.customerId: customerId,
        ApiParam.status: status,
      };
      if (date != null && time != null) {
        parameters[ApiParam.date] = date;
        parameters[ApiParam.time] = time;
      }

      if (otp != '' && status == 'completed') {
        parameters[ApiParam.otp] = otp;
      }

      if (proofData != null) {
        final List list = [];
        for (int i = 0; i < proofData.length; i++) {
          final element = proofData[i]['file'];

          final MultipartFile imagePart = await MultipartFile.fromFile(
            element.path,
            filename: p.basename(element.path),
            contentType: MediaType('image', proofData[i]['fileType']),
          );
          list.add(imagePart);
          if (status == 'started') {
            parameters['work_started_files[$i]'] = imagePart;
          } else {
            parameters['work_complete_files[$i]'] = imagePart;
          }
        }
      }
      if (additionalCharges != null) {
        for (int i = 0; i < additionalCharges.length; i++) {
          parameters['additional_charges[$i][name]'] =
              additionalCharges[i]['name'];
          parameters['additional_charges[$i][charge]'] =
              additionalCharges[i]['charge'];
        }
      }

      final Map<String, dynamic> response = await ApiClient.post(
        url: ApiUrl.updateBookingStatus,
        parameter: parameters,
        useAuthToken: true,
      );

      return {
        'error': response['error'],
        'message': response['message'],
        'data': response['data'],
      };
    } catch (e) {
      throw ApiException(e.toString());
    }
  }

  Future<BookingsModel?> fetchSingleBookingById({
    required String bookingId,
  }) async {
    try {
      final Map<String, dynamic> response = await ApiClient.post(
        url: ApiUrl.getBookings,
        parameter: {
          ApiParam.id: bookingId,
          ApiParam.limit: '1',
          ApiParam.offset: '0',
        },
        useAuthToken: true,
      );

      if (response['error'] || response['data'].isEmpty) {
        return null;
      }

      // Return the first booking from the response
      return BookingsModel.fromJson(Map.from(response['data']['data'][0]));
    } catch (e) {
      throw ApiException(e.toString());
    }
  }

  Future<DataOutput<TimeSlotModel>> getAllTimeSlots(String date) async {
    try {
      final Map<String, dynamic> response = await ApiClient.post(
        url: ApiUrl.getAvailableSlots,
        parameter: {ApiParam.date: date},
        useAuthToken: true,
      );

      final List<TimeSlotModel> timeSlotList =
          (response['data']['all_slots'] as List).map((element) {
            return TimeSlotModel.fromMap(element);
          }).toList();

      return DataOutput<TimeSlotModel>(
        total: 0,
        modelList: timeSlotList,
        extraData: ExtraData(
          data: {'error': response['error'], 'message': response['message']},
        ),
      );
    } catch (e) {
      rethrow;
    }
  }

  Future<Map<String, dynamic>> verifyBookingOTP({
    required String userId,
    required String otp,
  }) async {
    try {
      final Map<String, dynamic> response = await ApiClient.post(
        url: ApiUrl.verifyBookingOTP,
        parameter: {ApiParam.userId: userId, ApiParam.otp: otp},
        useAuthToken: true,
      );

      return {
        'error': response['error'] ?? false,
        'message': response['message'] ?? '',
      };
    } catch (e) {
      throw ApiException(e.toString());
    }
  }

  Future<List<int>> downloadInvoice({required final String bookingId}) async {
    try {
      final Map<String, dynamic> parameter = <String, dynamic>{
        ApiParam.orderId: bookingId,
      };

      final response = await ApiClient.downloadAPI(
        parameter: parameter,
        url: ApiUrl.downloadInvoice,
        useAuthToken: true,
      );

      return response;
    } catch (e) {
      throw ApiException(e.toString());
    }
  }
}
