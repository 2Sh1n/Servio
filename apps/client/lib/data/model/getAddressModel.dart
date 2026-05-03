class GetAddressModel {
  String? id;
  String? type;
  String? address;
  String? cityName;
  String? area;
  String? mobile;
  String? alternateMobile;
  String? pincode;
  String? cityId;
  String? landmark;
  String? state;
  String? country;
  String? lattitude;
  String? longitude;
  String? isDefault;

  GetAddressModel({
    this.id,
    this.type,
    this.address,
    this.cityName,
    this.area,
    this.mobile,
    this.alternateMobile,
    this.pincode,
    this.cityId,
    this.landmark,
    this.state,
    this.country,
    this.lattitude,
    this.longitude,
    this.isDefault,
  });

  GetAddressModel.fromJson(final Map<String, dynamic> json) {
    id = json['id']?.toString() ?? '0';
    type = json['type']?.toString() ?? '';
    address = json['address']?.toString() ?? '';
    cityName = json['city_name']?.toString() ?? '';
    area = json['area']?.toString() ?? '';
    mobile = json['mobile']?.toString() ?? '';
    alternateMobile = json['alternate_mobile']?.toString() ?? '';
    pincode = json['pincode']?.toString() ?? '';
    cityId = json['city_id']?.toString() ?? '';
    landmark = json['landmark']?.toString() ?? '';
    state = json['state']?.toString() ?? '';
    country = json['country']?.toString() ?? '';
    lattitude = json['lattitude']?.toString() ?? '';
    longitude = json['longitude']?.toString() ?? '';
    isDefault = json['is_default']?.toString() ?? '';
  }

  GetAddressModel copyWith({
    String? id,
    String? type,
    String? address,
    String? cityName,
    String? area,
    String? mobile,
    String? alternateMobile,
    String? pincode,
    String? cityId,
    String? landmark,
    String? state,
    String? country,
    String? lattitude,
    String? longitude,
    String? isDefault,
  }) {
    return GetAddressModel(
      id: id ?? this.id,
      type: type ?? this.type,
      address: address ?? this.address,
      cityName: cityName ?? this.cityName,
      area: area ?? this.area,
      mobile: mobile ?? this.mobile,
      alternateMobile: alternateMobile ?? this.alternateMobile,
      pincode: pincode ?? this.pincode,
      cityId: cityId ?? this.cityId,
      landmark: landmark ?? this.landmark,
      state: state ?? this.state,
      country: country ?? this.country,
      lattitude: lattitude ?? this.lattitude,
      longitude: longitude ?? this.longitude,
      isDefault: isDefault ?? this.isDefault,
    );
  }
}
