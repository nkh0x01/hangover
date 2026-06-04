import { Button, Card, Screen, Text } from "@ride360/ui";

import { WELCOME_ACTIONS } from "./driver-flow";

export default function DriverMinimalShell() {
  return (
    <Screen>
      <Text variant="title">Ride 360 Driver V2</Text>
      <Card>
        <Text variant="subtitle">{WELCOME_ACTIONS[0].title}</Text>
        <Text variant="caption">{WELCOME_ACTIONS[0].subtitle}</Text>
      </Card>
      <Card>
        <Text variant="subtitle">{WELCOME_ACTIONS[1].title}</Text>
        <Text variant="caption">{WELCOME_ACTIONS[1].subtitle}</Text>
      </Card>
      <Button onPress={() => undefined}>{WELCOME_ACTIONS[2].title}</Button>
    </Screen>
  );
}
