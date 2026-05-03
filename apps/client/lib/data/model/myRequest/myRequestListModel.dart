import 'package:e_demand/app/generalImports.dart';

class MyRequestListModel {
  String? id;
  String? userid;
  String? categoryId;
  String? serviceTitle;
  String? serviceShortDescription;
  String? minPrice;
  String? maxPrice;
  String? requestedStartDate;
  String? requestedStartTime;
  String? requestedEndDate;
  String? requestedEndTime;
  String? createdAt;
  String? updatedAt;
  String? status;
  String? translatedStatus;
  String? categoryName;
  String? categoryParentId;
  String? categoryImage;
  int? totalBids;
  List<Bidder?>? bidders;

  MyRequestListModel({
    this.id,
    this.userid,
    this.categoryId,
    this.serviceTitle,
    this.serviceShortDescription,
    this.minPrice,
    this.maxPrice,
    this.requestedStartDate,
    this.requestedStartTime,
    this.requestedEndDate,
    this.requestedEndTime,
    this.createdAt,
    this.updatedAt,
    this.status,
    this.translatedStatus,
    this.categoryName,
    this.categoryParentId,
    this.totalBids,
    this.bidders,
    this.categoryImage,
  });

  MyRequestListModel.fromJson(Map<String, dynamic> json) {
    id = json['id']?.toString() ?? '';
    userid = json['user_id']?.toString() ?? '';
    categoryId = json['category_id']?.toString() ?? '';
    serviceTitle = json['service_title']?.toString() ?? '';
    serviceShortDescription =
        json['service_short_description']?.toString() ?? '';
    minPrice = json['min_price']?.toString() ?? '';
    maxPrice = json['max_price']?.toString() ?? '';
    requestedStartDate = json['requested_start_date']?.toString() ?? '';
    requestedStartTime = json['requested_start_time']?.toString() ?? '';
    requestedEndDate = json['requested_end_date']?.toString() ?? '';
    requestedEndTime = json['requested_end_time']?.toString() ?? '';
    createdAt = json['created_at']?.toString() ?? '';
    updatedAt = json['updated_at']?.toString() ?? '';
    status = json['status']?.toString() ?? '';
    translatedStatus = (json['translated_status']?.toString() ?? '').isNotEmpty
        ? json['translated_status']!.toString()
        : status;

    categoryName = json['category_name']?.toString() ?? '';
    categoryParentId = json['category_parent_id']?.toString() ?? '';
    totalBids = json['total_bids']?.toInt() ?? 0;
    categoryImage = json['category_image']?.toString() ?? '';
    if (json['bidders'] != null) {
      bidders = <Bidder>[];
      json['bidders'].forEach((v) {
        bidders!.add(Bidder.fromJson(v));
      });
    }
  }

  Map<String, dynamic> toJson() {
    final Map<String, dynamic> data = Map<String, dynamic>();
    data['id'] = id;
    data['user_id'] = userid;
    data['category_id'] = categoryId;
    data['service_title'] = serviceTitle;
    data['service_short_description'] = serviceShortDescription;
    data['min_price'] = minPrice;
    data['max_price'] = maxPrice;
    data['requested_start_date'] = requestedStartDate;
    data['requested_start_time'] = requestedStartTime;
    data['requested_end_date'] = requestedEndDate;
    data['requested_end_time'] = requestedEndTime;
    data['created_at'] = createdAt;
    data['updated_at'] = updatedAt;
    data['status'] = status;
    data['translated_status'] = translatedStatus;
    data['category_name'] = categoryName;
    data['category_parent_id'] = categoryParentId;
    data['total_bids'] = totalBids;
    data['category_image'] = categoryImage;
    data['bidders'] =
        bidders != null ? bidders!.map((v) => v?.toJson()).toList() : null;
    return data;
  }
}
