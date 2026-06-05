import { useEffect, useMemo, useState } from "react";
import { View } from "react-native";

import { Card, Text } from "@ride360/ui";

import {
  buildDashboardMapState,
  type ActiveRideOffer,
  type DashboardMapMarker,
  type DriverRide,
  type LocationPoint,
} from "./driver-dashboard";

type MapsModule = typeof import("react-native-maps");

type MapLoadState =
  | { status: "loading"; maps?: undefined; error?: undefined }
  | { status: "ready"; maps: MapsModule; error?: undefined }
  | { status: "failed"; maps?: undefined; error: string };

export function DriverMapPreview({
  activeOffer,
  activeRide,
  currentLocation,
}: {
  activeOffer: ActiveRideOffer | null;
  activeRide: DriverRide | null;
  currentLocation: LocationPoint | null;
}) {
  const [loadState, setLoadState] = useState<MapLoadState>({
    status: "loading",
  });
  const mapState = useMemo(
    () => buildDashboardMapState({ activeOffer, activeRide, currentLocation }),
    [activeOffer, activeRide, currentLocation],
  );

  useEffect(() => {
    let mounted = true;
    void import("react-native-maps")
      .then((maps) => {
        if (!mounted) return;
        setLoadState({ status: "ready", maps });
      })
      .catch((error: unknown) => {
        if (!mounted) return;
        setLoadState({
          status: "failed",
          error: error instanceof Error ? error.message : String(error),
        });
      });

    return () => {
      mounted = false;
    };
  }, []);

  if (loadState.status !== "ready") {
    return (
      <Card>
        <Text variant="subtitle">რუკა</Text>
        <Text variant="caption">
          {loadState.status === "loading"
            ? "რუკა იტვირთება."
            : `რუკა ვერ ჩაიტვირთა: ${loadState.error}`}
        </Text>
      </Card>
    );
  }

  const MapView = loadState.maps.default;
  const Marker = loadState.maps.Marker;
  const Polyline = loadState.maps.Polyline;
  const provider =
    process.env.EXPO_PUBLIC_MAP_PROVIDER === "google" &&
    process.env.EXPO_PUBLIC_GOOGLE_MAPS_ENABLED === "true"
      ? loadState.maps.PROVIDER_GOOGLE
      : undefined;
  const route = routeCoordinates(mapState.markers);
  const mapKey = mapState.markers
    .map((marker) => `${marker.id}:${marker.lat}:${marker.lng}`)
    .join("|");

  return (
    <Card>
      <Text variant="subtitle">რუკა</Text>
      <View
        style={{
          borderColor: "#d7dce5",
          borderRadius: 8,
          borderWidth: 1,
          height: 220,
          overflow: "hidden",
        }}
      >
        <MapView
          key={mapKey}
          initialRegion={mapState.region}
          provider={provider}
          showsMyLocationButton={Boolean(currentLocation)}
          showsUserLocation={Boolean(currentLocation)}
          style={{ flex: 1 }}
        >
          {mapState.markers.map((marker) => (
            <Marker
              coordinate={{ latitude: marker.lat, longitude: marker.lng }}
              key={marker.id}
              pinColor={pinColor(marker.kind)}
              title={marker.label}
            />
          ))}
          {route.length >= 2 ? (
            <Polyline
              coordinates={route}
              strokeColor="#1557d8"
              strokeWidth={3}
            />
          ) : null}
        </MapView>
      </View>
      <Text variant="caption">
        {mapState.markers.length > 0
          ? "რუკაზე ჩანს მძღოლი, აყვანის ან დანიშნულების წერტილები."
          : "წერტილები გამოჩნდება ცვლის დაწყების ან შეკვეთის მიღების შემდეგ."}
      </Text>
    </Card>
  );
}

function routeCoordinates(markers: DashboardMapMarker[]) {
  return markers
    .filter((marker) => marker.kind === "pickup" || marker.kind === "dropoff")
    .map((marker) => ({
      latitude: marker.lat,
      longitude: marker.lng,
    }));
}

function pinColor(kind: DashboardMapMarker["kind"]) {
  switch (kind) {
    case "driver":
      return "#1557d8";
    case "pickup":
      return "#198754";
    case "dropoff":
      return "#c2410c";
    default:
      return "#1557d8";
  }
}
