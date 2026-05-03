import '../../app/generalImports.dart';

class CommissionAmountRepository {
  //-------------------------------Withdrawal Request Start

  Future<DataOutput<WithdrawalModel>> fetchWithdrawalRequests({
    required Map<String, dynamic> parameter,
  }) async {
    try {
      final Map<String, dynamic> response = await ApiClient.post(
        url: ApiUrl.getWithdrawalRequest,
        parameter: parameter,
        useAuthToken: true,
      );

      final List<WithdrawalModel> modelList = (response['data'] as List)
          .map((data) => WithdrawalModel.fromJson(data))
          .toList();

      return DataOutput(
        total: int.parse(response['total']?.toString() ?? '0'),
        modelList: modelList,
        extraData: ExtraData(data: (response["balance"] ?? "0").toString()),
      );
    } catch (e) {
      throw ApiException(e.toString());
    }
  }

  Future<Map<String, String>> sendWithdrawalRequest({
    required String amount,
  }) async {
    try {
      final Map<String, dynamic> parameter = {
        ApiParam.amount: amount,
        ApiParam.userType: 'partner',
      };

      final Map<String, dynamic> response = await ApiClient.post(
        url: ApiUrl.sendWithdrawalRequest,
        parameter: parameter,
        useAuthToken: true,
      );

      return {
        'balance': response['balance']?.toString() ?? '0',
        'message': response['message']?.toString() ?? '',
      };
    } catch (e) {
      throw ApiException(e.toString());
    }
  }

  //-------------------------------Withdrawal Request End

  //-------------------------------Cash collection Start

  Future<DataOutput<CashCollectionModel>> fetchCashCollectionHistory({
    required Map<String, dynamic> parameter,
  }) async {
    try {
      final Map<String, dynamic> response = await ApiClient.post(
        url: ApiUrl.getCashCollection,
        parameter: parameter,
        useAuthToken: true,
      );

      final List<CashCollectionModel> cashCollectionData =
          (response['data'] as List)
              .map((data) => CashCollectionModel.fromJson(data))
              .toList();

      return DataOutput(
        total: int.parse(response['total'] ?? '0'),
        modelList: cashCollectionData,
        extraData: ExtraData(
          data: {
            'totalPayableCommission': response['payable_commision'].toString(),
          },
        ),
      );
    } catch (e) {
      throw ApiException(e.toString());
    }
  }

  //-------------------------------Cash collection end
  //-------------------------------Settlement  Start

  Future<DataOutput<SettlementModel>> fetchSettlementHistory({
    required Map<String, dynamic> parameter,
  }) async {
    try {
      final Map<String, dynamic> response = await ApiClient.post(
        url: ApiUrl.getSettlementHistory,
        parameter: parameter,
        useAuthToken: true,
      );

      final List<SettlementModel> settlementData = (response['data'] as List)
          .map((data) => SettlementModel.fromJson(data))
          .toList();

      return DataOutput(
        total: int.parse((response['total'] ?? '0').toString()),
        modelList: settlementData,
        extraData: ExtraData(data: response["balance"] ?? "0"),
      );
    } catch (e) {
      throw ApiException(e.toString());
    }
  }

  //-------------------------------Settlement  End
  //-------------------------------Booking Payment Management Start

  Future<DataOutput<BookingPaymentDataModel>>
  fetchBookingPaymentManagementData({
    required Map<String, dynamic> parameter,
  }) async {
    try {
      final Map<String, dynamic> response = await ApiClient.post(
        url: ApiUrl.getBookingSettleManagementHistory,
        parameter: parameter,
        useAuthToken: true,
      );

      final List<BookingPaymentDataModel> settlementData =
          (response['data'] as List)
              .map((data) => BookingPaymentDataModel.fromJson(data))
              .toList();

      return DataOutput(
        total: int.parse((response['total'] ?? '0').toString()),
        modelList: settlementData,
      );
    } catch (e) {
      throw ApiException(e.toString());
    }
  }

  //-------------------------------Booking Payment Management End
}
